<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\GoogleUser;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\UserCourse;
use App\Services\NotificationService;
use Exception;
use Google\Client;
use Google\Service\AndroidPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredGooglePlayCourses extends Command
{
    protected $signature = 'subscriptions:cleanup-google-play
                            {--dry-run : Print actions without applying changes}
                            {--user-id= : Limit cleanup to a specific user_id}';

    protected $description = 'Reconcile expired Google Play user_courses with Play Store state. Deactivate when no active subscription, refresh validity when auto-renew succeeded.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userIdFilter = $this->option('user-id');

        $query = UserCourse::query()
            ->where('status', 1)
            ->where('valid_to', '<', now())
            ->where('meta_data', 'LIKE', '%"provider":"google_play"%');

        if (!empty($userIdFilter)) {
            $query->where('user_id', (int) $userIdFilter);
        }

        $rows = $query->orderBy('id')->get();

        $this->line('candidates=' . $rows->count() . ' dryRun=' . ($dryRun ? '1' : '0'));

        $renewed = 0;
        $deactivated = 0;
        $skipped = 0;

        $service = null;
        try {
            $service = $this->buildAndroidPublisherService();
        } catch (Exception $e) {
            $this->error('Failed to init AndroidPublisher: ' . $e->getMessage());
            return self::FAILURE;
        }

        $packageName = config('app.package_name');

        foreach ($rows as $uc) {
            $meta = json_decode($uc->meta_data, true) ?: [];
            $purchaseToken = $meta['purchase_token'] ?? null;

            if (empty($purchaseToken)) {
                $this->warn("uc={$uc->id} no purchase_token, skip");
                $skipped++;
                continue;
            }

            try {
                $subscription = $service->purchases_subscriptionsv2->get($packageName, $purchaseToken);
                $state = $subscription->getSubscriptionState();
                $lineItems = $subscription->getLineItems();

                $activeStates = [
                    'SUBSCRIPTION_STATE_ACTIVE',
                    'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
                ];

                if (in_array($state, $activeStates, true) && !empty($lineItems)) {
                    $newExpiry = date('Y-m-d H:i:s', strtotime($lineItems[0]->getExpiryTime()));
                    $orderId = $subscription->getLatestOrderId();

                    $this->info("uc={$uc->id} user={$uc->user_id} course={$uc->course_id} state={$state} → renew valid_to={$newExpiry} order_id={$orderId}");

                    if (!$dryRun) {
                        $meta['order_id'] = $orderId;
                        $meta['cleanup_renewed_at'] = now()->toDateTimeString();
                        $uc->update([
                            'valid_to' => $newExpiry,
                            'status' => 1,
                            'meta_data' => json_encode($meta),
                        ]);

                        $this->recordRenewalPaymentAndNotify($uc, $orderId, $newExpiry, $meta);
                    }

                    $renewed++;
                    continue;
                }

                $this->info("uc={$uc->id} user={$uc->user_id} course={$uc->course_id} state={$state} → deactivate");
                if (!$dryRun) {
                    $meta['cleanup_deactivated_at'] = now()->toDateTimeString();
                    $meta['cleanup_last_state'] = $state;
                    $uc->update([
                        'status' => 0,
                        'meta_data' => json_encode($meta),
                    ]);
                }
                $deactivated++;
            } catch (Exception $e) {
                Log::warning('CleanupExpiredGooglePlay: API call failed, skipping', [
                    'user_course_id' => $uc->id,
                    'user_id' => $uc->user_id,
                    'course_id' => $uc->course_id,
                    'token_prefix' => substr($purchaseToken, 0, 20),
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
                $this->warn("uc={$uc->id} api_error skip: " . $e->getMessage());
                $skipped++;
            }
        }

        UserCourse::where('valid_to', '<', now())
            ->where('status', 1)
            ->delete();

        $this->line("done renewed={$renewed} deactivated={$deactivated} skipped={$skipped} candidates={$rows->count()}");
        return self::SUCCESS;
    }

    private function buildAndroidPublisherService(): AndroidPublisher
    {
        $client = new Client();
        $keyPath = base_path(config('services.google.service_account_json'));
        $client->setAuthConfig($keyPath);
        $client->addScope(AndroidPublisher::ANDROIDPUBLISHER);
        return new AndroidPublisher($client);
    }

    private function recordRenewalPaymentAndNotify(UserCourse $uc, ?string $orderId, string $newExpiry, array $meta): void
    {
        try {
            $user = GoogleUser::find($uc->user_id);
            if (!$user) {
                return;
            }

            $paymentId = !empty($orderId)
                ? $orderId
                : 'gp_cleanup_' . substr(hash('sha256', ($meta['purchase_token'] ?? '') . ':' . $newExpiry), 0, 24);

            $course = Course::find($uc->course_id);
            $courseName = $course && $course->name ? $course->name : 'Course';
            $subscriptionData = is_array($course?->subscription)
                ? $course->subscription
                : json_decode($course?->subscription ?? '[]', true);
            $plan = $uc->subscription_type ?: 'monthly';
            $amount = isset($subscriptionData[$plan]['amount']) ? floatval($subscriptionData[$plan]['amount']) : 0.0;

            $payment = Payment::firstOrCreate(
                ['payment_id' => $paymentId],
                [
                    'amount' => $amount,
                    'currency' => 'INR',
                    'status' => 'captured',
                    'source' => 'google_play',
                    'email' => $user->email,
                    'contact' => $user->phone_number,
                    'user_id' => $user->id,
                    'course_id' => $uc->course_id,
                    'method' => 'google_play',
                ]
            );

            if (!$payment->wasRecentlyCreated) {
                return;
            }

            try {
                (new NotificationService())->send([
                    'user_id' => $user->id,
                    'title' => 'Subscription Renewed',
                    'message' => "Your subscription for {$courseName} is now active. Valid till {$newExpiry}.",
                    'type' => 'subscription',
                    'source' => 'system',
                ], [$user->id]);
            } catch (Exception $notifEx) {
                Log::warning('CleanupExpiredGooglePlay: notification dispatch failed', [
                    'user_id' => $user->id,
                    'course_id' => $uc->course_id,
                    'exception' => $notifEx->getMessage(),
                ]);
            }
        } catch (Exception $e) {
            Log::warning('CleanupExpiredGooglePlay: record renewal failed', [
                'user_course_id' => $uc->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
