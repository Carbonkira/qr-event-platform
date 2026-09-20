<?php

namespace Tests\Feature;

use App\Mail\ApplicationDecisionMail;
use App\Mail\ApplicationReceivedMail;
use App\Models\Organizer;
use App\Services\AdminContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How an applicant reaches the admin. Replies to the application emails are
 * the part that matters: those mails go out from MAIL_FROM_ADDRESS (a
 * sending-only address), so without a Reply-To an applicant's answer would
 * land somewhere nobody reads.
 */
class AdminContactTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $email, ?string $number = null): Organizer
    {
        $admin = Organizer::create(['name' => 'Admin', 'email' => $email, 'password' => bcrypt('password123'), 'contact_number' => $number]);
        $admin->forceFill(['role' => 'admin', 'approval_status' => 'approved'])->save();

        return $admin;
    }

    public function test_an_explicit_email_wins_over_any_admins_profile(): void
    {
        $this->makeAdmin('owner@example.com', '0999 888 7777');
        config(['services.admin.contact_email' => 'support@qrmeets.example']);

        $this->assertSame('support@qrmeets.example', AdminContact::email());
    }

    public function test_without_one_it_is_the_admin_who_gave_a_number_so_number_and_email_belong_to_one_person(): void
    {
        $this->makeAdmin('first@example.com'); // earlier account, no number
        $this->makeAdmin('second@example.com', '0999 888 7777');

        $this->assertSame('0999 888 7777', AdminContact::number());
        $this->assertSame('second@example.com', AdminContact::email());
    }

    public function test_with_no_numbers_anywhere_it_falls_back_to_the_first_admin(): void
    {
        $this->makeAdmin('first@example.com');
        $this->makeAdmin('second@example.com');

        $this->assertNull(AdminContact::number());
        $this->assertSame('first@example.com', AdminContact::email());
    }

    public function test_an_organizer_who_is_not_an_admin_is_never_the_contact(): void
    {
        $organizer = Organizer::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => bcrypt('password123'), 'contact_number' => '0917 123 4567']);
        $organizer->forceFill(['approval_status' => 'approved'])->save();

        $this->assertNull(AdminContact::email());
        $this->assertNull(AdminContact::number());
    }

    public function test_the_received_email_replies_to_the_admin(): void
    {
        $this->makeAdmin('owner@example.com', '0999 888 7777');

        $mail = new ApplicationReceivedMail('Ana Reyes', 'organizer account');
        $mail->render();

        $this->assertTrue($mail->hasReplyTo('owner@example.com'));
    }

    public function test_both_decision_emails_reply_to_the_admin(): void
    {
        $this->makeAdmin('owner@example.com', '0999 888 7777');

        foreach ([true, false] as $approved) {
            $mail = new ApplicationDecisionMail('Ana Reyes', 'organizer account', $approved);
            $mail->render();

            $this->assertTrue($mail->hasReplyTo('owner@example.com'), $approved ? 'approved email' : 'rejected email');
        }
    }

    public function test_the_reply_to_is_the_configured_inbox_and_carries_the_app_name_not_the_admins(): void
    {
        $this->makeAdmin('owner@example.com', '0999 888 7777');
        config(['services.admin.contact_email' => 'support@qrmeets.example', 'mail.from.name' => 'QRMeets']);

        $mail = new ApplicationReceivedMail('Ana Reyes', 'organizer account');
        $mail->render();

        $this->assertTrue($mail->hasReplyTo('support@qrmeets.example', 'QRMeets'));
        $this->assertFalse($mail->hasReplyTo('owner@example.com'));
    }

    public function test_with_no_admin_at_all_the_emails_still_build_and_simply_have_no_reply_to(): void
    {
        $mail = new ApplicationReceivedMail('Ana Reyes', 'organizer account');
        $mail->render();

        $this->assertEmpty($mail->replyTo);
    }
}
