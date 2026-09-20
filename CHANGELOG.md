# Changelog

All notable changes to QRMeets. Newest first.

## September 9-20, 2026

### Accounts and access
- **Separate Organizer and Participant accounts.** Each has its own login, signup and password reset, with an Organizer/Participant switch on the login page. Existing accounts were split by what they had done. The 4 people who had both hosted and attended got one account of each type.
- **Admin role.** The admin creates organizations, approves organizer accounts, and approves every event. An admin can manage any organization without belonging to it, create events with no organization, delete organizations (their events stay, unaffiliated), and promote or demote organization owners.
- **Admin approval for organizers.** New organizer accounts start as pending and can't host anything until the admin approves them. Existing organizers were approved automatically.
- **Email verification** is now required for almost every action while logged in. Log out, resend link and Profile still work unverified.
- **Role labels and colors.** Admin, Organizer and Participant badges show in the profile menu and Profile page. The site's accent color follows the role: gold for admin, violet for organizer, blue for participant. Logged-out visitors see the original pink.
- **Removed:** the Connections feature and the Certificate of Attendance option.

### Events and organizations
- **Organizations are admin-created.** Organizers join by invite or by picking one at signup. An event must be created under an organization the organizer belongs to, and every event needs admin approval, with an email to the admin.
- **Organizer application (Sept 20).** Signup now asks for a full name, email, contact number, and organization. The organization is picked from the list, or requested with a name and an address. The admin approves and creates the organization in one click, making the applicant its owner.
- **Approvals page (Sept 20).** Click any application to see its full details. There are Pending, Approved and Rejected views for events and organizers, grouped by month, newest first. The admin also sees the creator's name, institution, email and number.
- **Real "Created by".** Event pages, listings and admin views show the actual organizer's name and picture, not a typed-in name.
- **Newest event date first.** Every event list is now ordered this way.

### Registration and payments
- **Paid events:** the QR pass is withheld until the organizer verifies payment. Attendees get a "payment verified" or "payment rejected" email.
- **Payment is handled outside the system.** Organizers advertise where to pay on their own channels. Participants submit proof: mode of payment, where they paid, amount, date, reference number, note and screenshot.
- **OR number.** The organizer enters an OR/receipt number when verifying, and it appears on the confirmation email.
- **Organizers are emailed** whenever a participant finishes registering.
- **"Forgot your password?"** is now on the event registration login step, and returns the participant to that registration after the reset.
- **Fixed:** registration errors that showed a single letter, and the duplicate name and email fields.

### Emails
- Applicants get "We have received your application for your [organizer account / event]. You can contact us here [admin number]" as soon as they apply.
- The admin is emailed for each new organizer application (before, only for events). The Approvals badge counts both.
- Applicants are emailed when the admin decides. An approved organizer is told which organization they were added to (and as owner or member) with a log-in button. An approved event links straight to its page. A rejection includes the admin's number instead of a button. Each decision is emailed once, and an admin deciding on their own event isn't emailed.

### Security
- **Pass links:** anyone could step through registration numbers and see other people's names and QR codes. Every pass link now carries a private token. Links already emailed keep working.
- **Payment verification:** any logged-in user could verify a payment for any event. It now requires being on that event's organization.
- **Feedback:** anyone could post feedback for any registration. It now requires that the participant was checked in, holds their own pass token, and submits only once.
- **Organization address** (added for verifying an organization) is never shown publicly.

### Deployment notes
- Three new database migrations run automatically on deploy. They only add columns.
- Set the admin's contact number on the admin **Profile page**, or set `ADMIN_CONTACT_NUMBER` on Railway. Until then the applicant emails leave out the "you can contact us" line.
- Events approved or rejected before Sept 20 show their last-updated time as the decision date in the Approvals history.

### Known follow-ups
- QR code values are still sequential. Scanning one needs an approved organizer login, and the exposure through pass links is closed.
- The paper's ERD, Data Dictionary and System Design still describe a single account type.
