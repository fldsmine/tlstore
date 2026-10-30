# MASTER IMPLEMENTATION PROMPT: INTEGRATE THE TRUSTLENS PLATFORM INTO THE EXISTING PROJECT

## 1. ROLE AND OBJECTIVE

Act as a senior full-stack software architect, backend engineer, frontend engineer, database architect, API engineer, security engineer and QA engineer.

You are working on an **existing software project**. Your responsibility is to extend the existing project by implementing a complete TrustLens application-testing and paid-feedback platform.

This is NOT a request to create a standalone application, redesign the entire project, or build a separate demonstration.

You must study the existing codebase, understand its architecture, identify the correct places to integrate the new functionality, and implement the entire feature using the project's existing technologies and coding conventions.

The objective is to transform the provided HTML/JavaScript prototype into a fully functional, database-driven, API-powered and production-ready feature.

### Reference file

There is an HTML file named:

`project-overview.html`

It is located in the `raw-data` directory of the existing project.

This file demonstrates the intended interface, user experience, navigation, workflows, questionnaires, wallet, proxy purchasing interface and other functionality.

**You MUST inspect this file completely before implementation.**

Use it as the primary visual and functional reference for the new feature.

You are permitted to improve its implementation, correct its weaknesses, adjust the workflows and introduce additional functionality where necessary to make the system secure, scalable and production-ready.

Do not blindly copy its implementation because it is currently a frontend prototype.

Also access the `raw-data` for more info concerning the new features.
---

## 2. FIRST PHASE: INSPECT THE EXISTING PROJECT

Before writing or modifying code, thoroughly inspect the existing project.

Identify:

1. The programming languages and frameworks currently being used.
2. The frontend architecture and component structure.
3. The backend architecture and existing services.
4. The database technology and database structure.
5. Existing authentication and authorization mechanisms.
6. Existing user roles and permission systems.
7. Existing API architecture and response conventions.
8. Existing payment integrations.
9. Existing wallet, transaction or financial systems, if available.
10. Existing file and image upload functionality.
11. Existing notification infrastructure.
12. Existing admin dashboard.
13. Existing developer or merchant dashboard, if available.
14. Existing third-party integrations.
15. Existing testing infrastructure.
16. Existing deployment and environment configuration.

Examine the existing database models, migrations, controllers, services, repositories, middleware, routes, API resources, frontend components and configuration files.

Determine which existing components can be reused.

### Integration rules

- Do not create a new project.
- Do not introduce an unnecessary second backend.
- Do not replace the existing framework.
- Do not create duplicate authentication systems.
- Do not create duplicate wallet systems if an existing one can be extended.
- Do not introduce another database unnecessarily.
- Do not break existing functionality.
- Do not modify unrelated modules.
- Follow existing naming conventions and coding standards.
- Reuse existing services and components whenever appropriate.
- Maintain backward compatibility with existing APIs where possible.
- Do not delete existing database tables or user records.
- Do not run destructive database commands against existing environments.

If a new dependency is necessary, explain its purpose and ensure it is compatible with the existing project.

After completing your inspection, prepare a short implementation plan for yourself and proceed with implementation. Do not stop after producing the plan or asking unnecessary questions.

If something is genuinely ambiguous or requires an external credential, implement the appropriate integration structure and document the required configuration.

---

# 3. FEATURE OVERVIEW

Implement a complete TrustLens platform that allows users to:

1. Create an account and authenticate.
2. Access available application-testing assignments.
3. View application information and developer-provided screenshots.
4. Test applications and provide honest private feedback.
5. Complete structured questionnaires.
6. Submit testing reports.
7. Have submissions verified by the platform.
8. Earn rewards for approved testing assignments.
9. View their earnings and transaction history.
10. Purchase optional testing services or region-specific testing sessions where legally and technically appropriate.
11. Convert their earnings into supported currencies for display.
12. Request withdrawals when eligible.

The platform should also provide administrative functionality to manage applications, testing assignments, users, submissions, rewards, payments, withdrawals, proxy integrations and reports.

Where appropriate, application developers should have their own dashboard to manage their applications and view approved testing insights.

The system must be modular, maintainable and extensible.

---

# 4. USER ROLES AND PERMISSIONS

Implement the following roles using the existing authorization architecture.

### A. Regular user / tester

Can:

- Register and log in.
- Update their profile.
- View available testing assignments.
- View application details.
- Participate in eligible testing assignments.
- Submit feedback and questionnaires.
- View submission history.
- View reward balances.
- View pending and approved rewards.
- View wallet transactions.
- Request eligible withdrawals.
- View notifications.
- View testing session information.

### B. Administrator

Can:

- Access the administration dashboard.
- Manage users.
- Manage application developers.
- Manage application listings.
- Create, edit and deactivate testing assignments.
- Manage testing questionnaires.
- Review submitted testing reports.
- Approve or reject submissions.
- Provide rejection reasons.
- Manage reward configurations.
- Manage wallet transactions.
- Manage withdrawal requests.
- Manage payment transactions.
- Manage proxy configurations.
- View fraud alerts.
- View analytics and reports.
- Manage currencies and exchange-rate settings.
- Configure platform settings.

### C. Application developer

If the existing project supports developer or merchant accounts, integrate this role into that system. Otherwise, implement it as an additional role.

Developers should be able to:

- Register or apply for developer access.
- Submit application information.
- Upload application screenshots.
- Provide store listing URLs.
- Add application descriptions.
- Provide testing instructions.
- Submit applications for administrative approval.
- Create or request testing campaigns.
- Specify testing requirements and target regions.
- View testing progress.
- View aggregated, anonymized feedback.
- View testing reports.
- Track campaign status.

Developers must not be able to manipulate tester submissions, directly alter rewards or approve their own campaigns.

---

# 5. AUTHENTICATION AND USER MANAGEMENT

Implement complete authentication using the existing project's authentication infrastructure.

Required functionality:

- User registration.
- Email and password login.
- Google OAuth authentication.
- Email verification.
- Password reset.
- Password change.
- Logout.
- Session/token management.
- Profile management.
- Country selection.
- Account status management.

Google authentication must use a real OAuth integration, not a simulated login button.

Ensure that users cannot access protected APIs or dashboard pages without valid authentication.

Apply proper authorization checks on the backend.

Never trust user IDs, wallet amounts, roles or account status supplied by the frontend.

---

# 6. TESTING APPLICATION MANAGEMENT

Convert the static application array in the prototype into a proper database-driven application management module.

The prototype currently contains examples such as:

- FitPulse Tracker.
- CashBloom – Instant Loans.
- NairaWise Budget.

Treat these as demonstration records, not permanent hardcoded production data.

### Application fields

Each application should support:

- Application ID.
- Application name.
- Short name.
- Application icon.
- Developer name.
- Developer ID.
- Category.
- Description.
- Supported store.
- Google Play listing URL.
- Apple App Store listing URL.
- Samsung Galaxy Store listing URL.
- Package identifier or bundle identifier.
- Application version.
- Screenshots.
- Supported countries.
- Target testing regions.
- Installation instructions.
- Testing instructions.
- Privacy policy URL.
- Support URL.
- Application status.
- Creation date.
- Updated date.

Supported stores should include:

1. Google Play Store.
2. Apple App Store.
3. Samsung Galaxy Store.

Design the application model so that additional stores can be introduced later.

### Application management features

Administrators should be able to:

- Create applications.
- Edit applications.
- Delete or archive applications where permitted.
- Approve or reject developer submissions.
- Upload screenshots.
- Update application information.
- Configure testing eligibility.
- Activate or deactivate applications.

Use proper file validation and secure storage for uploaded images.

Do not use the generated SVG screenshots from the prototype as actual application screenshots.

---

# 7. TESTING ASSIGNMENTS AND CAMPAIGNS

Create a complete assignment management system.

An application and a testing assignment should be separate entities.

One application should be capable of having multiple testing campaigns.

### Campaign fields

- Campaign ID.
- Application ID.
- Campaign title.
- Description.
- Reward amount.
- Maximum number of participants.
- Current participant count.
- Target country.
- Target operating system.
- Required application version.
- Testing instructions.
- Questionnaire configuration.
- Minimum testing duration.
- Campaign start date.
- Campaign end date.
- Campaign status.
- Created by.
- Created date.

### Campaign statuses

- Draft.
- Pending approval.
- Active.
- Paused.
- Completed.
- Cancelled.

### Assignment functionality

Users should see available assignments based on:

- Their country.
- Device compatibility.
- Operating system.
- Campaign eligibility.
- Remaining participant slots.
- Previous participation.
- Account status.
- Other campaign restrictions.

Prevent duplicate participation where the campaign does not permit it.

Implement assignment reservation and expiration where appropriate so multiple users cannot claim the same limited slot.

The frontend must obtain available assignments from the backend API.

Do not hardcode campaign data in JavaScript.

---

# 8. TESTING WORKFLOW

Use the workflow demonstrated in `project-overview.html` as the foundation.

Improve it where necessary.

### Step 1: Select an assignment

The user opens the dashboard and sees available testing assignments.

Each assignment should display:

- Application icon.
- Application name.
- Developer name.
- Category.
- Store.
- Application version.
- Reward amount.
- Testing requirements.
- Estimated testing duration.
- Assignment status.

### Step 2: View application details

Display:

- Application description.
- Developer information.
- Screenshots.
- Store listing.
- Privacy policy.
- Testing instructions.

Allow users to enlarge screenshots.

### Step 3: Begin testing

Before starting, verify eligibility through the backend.

Create a testing session and record:

- User ID.
- Assignment ID.
- Application ID.
- Session ID.
- Start time.
- Device information where permitted.
- Session status.

### Step 4: Complete the questionnaire

Implement a configurable questionnaire engine.

Support:

- Single-choice questions.
- Multiple-choice questions.
- Rating questions.
- Text questions.
- Yes/no questions.
- Confirmation questions.

Each question should support:

- Question title.
- Description.
- Answer options.
- Required or optional status.
- Minimum word count.
- Maximum word count.
- Validation rules.
- Display order.
- Question category.

Questionnaires should be configurable by administrators rather than permanently embedded in frontend JavaScript.

### Step 5: Testing quality and safety questions

Use the prototype's safety and quality assessment sections as inspiration.

Questions may cover:

- Does the actual application match its store screenshots?
- Is the application easy to understand?
- Does the application perform as expected?
- Are there crashes or technical problems?
- Does the application request unnecessary permissions?
- Are there unexpected fees?
- Are there misleading financial claims?
- Are the developer's support details available?
- Are the application's privacy disclosures clear?
- Does the application appear trustworthy?

Allow users to report suspicious behavior.

For sensitive applications, provide additional questions based on the application category.

### Step 6: Written feedback

Users should provide genuine observations in their own words.

Implement configurable minimum and maximum word counts.

Prevent empty submissions.

Do not encourage copied responses or predetermined favourable answers.

Remove the prototype's instruction that testers must select a particular rating to pass a quality check.

Instead, use neutral attention checks and genuine response-quality validation that do not dictate a positive or negative opinion.

### Step 7: Submit testing report

When the user submits:

- Validate all required answers.
- Verify the active testing session.
- Verify assignment ownership.
- Prevent duplicate submissions.
- Save all answers.
- Save the testing report.
- Record the submission timestamp.
- Calculate the submission's eligibility for a reward.
- Set the submission status to Pending Verification.

Return a unique submission reference.

Do not trust frontend calculations for reward eligibility.

### Step 8: Verification

Administrators should be able to review submissions.

Available actions:

- Approve.
- Reject.
- Request additional information.
- Flag for investigation.

Every decision should record:

- Administrator ID.
- Decision.
- Decision date.
- Reason.
- Relevant notes.

If a submission is rejected, the user should receive a clear reason.

Implement an optional automated quality-assessment service, but automated flags must not automatically be treated as proof of fraud.

### Step 9: Reward processing

Only approved and eligible submissions should receive withdrawable rewards.

Use a pending-reward mechanism until verification is completed.

When approved:

- Create a reward transaction.
- Credit the user's available wallet balance.
- Update the submission status.
- Notify the user.

When rejected:

- Do not credit an available reward.
- Update the submission status.
- Record the rejection reason.

All financial operations must be atomic and idempotent.

---

# 9. DASHBOARD

Reproduce and improve the dashboard demonstrated in the reference file.

### Dashboard statistics

Display:

- Available testing assignments.
- Total approved earnings.
- Pending rewards.
- Total completed assignments.
- Pending verification count.
- Rejected submissions.

### Assignment list

Show available assignments with:

- Application icon.
- Application name.
- Category.
- Store.
- Developer.
- Reward amount.
- Testing duration.
- Eligibility status.
- Action button.

### Testing eligibility

If a user is not eligible for a campaign, explain why.

For example:

- Unsupported country.
- Campaign already completed.
- Device incompatibility.
- Assignment limit reached.
- Account restriction.

### Important

All dashboard figures must come from authenticated backend APIs.

Do not calculate financial statistics exclusively in the browser.

---

# 10. PROXY SESSION MANAGEMENT

The reference prototype contains a proxy purchasing and activation feature.

Convert this into a proper integration module.

First inspect whether the existing project already has a proxy provider or network-session service.

If not, create a provider abstraction that supports future integration with legitimate proxy or device-testing services.

### Proxy plans

The prototype demonstrates:

**Day Pass**
- ₦300.
- 24 hours.
- Up to 10 testing assignments.

**Weekly Pass**
- ₦1,500.
- 7 days.
- Up to 10 testing assignments per day.

These are reference values, not immutable production prices.

Administrators must be able to configure:

- Plan name.
- Price.
- Duration.
- Usage limit.
- Eligible regions.
- Plan status.

### Proxy management requirements

- Purchase a plan.
- Verify payment.
- Activate a session.
- Generate a unique session reference.
- Record activation and expiration times.
- Monitor usage limits.
- Display remaining session time.
- Automatically expire sessions.
- Allow administrators to revoke sessions.
- Maintain transaction records.

### Important technical requirements

Do not simulate proxy activation using JavaScript timers.

A successful payment must not automatically imply that a real proxy exists.

Only activate a session after the external provider confirms successful provisioning.

Never expose proxy credentials or provider API secrets to the frontend.

Do not use proxies to circumvent app-store restrictions, manipulate public reviews, evade security controls or misrepresent user location.

If region-specific testing is needed, use authorized testing environments and provider-supported configurations.

If no proxy provider is configured, clearly indicate that the service is unavailable rather than generating fake sessions.

The proxy feature should be modular and configurable.

---

# 11. PAYMENT INTEGRATION

Replace the prototype's fake card payment form and simulated payment processing with a real payment integration.

First inspect the existing project's payment architecture.

Reuse an existing payment provider if available.

If there is no existing provider, implement a payment-provider abstraction that can support a suitable provider for the deployment market.

For a Nigerian deployment, evaluate providers such as Paystack or Flutterwave based on availability, supported payment methods and integration requirements.

### Payment functionality

- Initiate payment.
- Generate a unique payment reference.
- Redirect to the provider checkout where appropriate.
- Verify payment server-side.
- Process verified webhooks.
- Prevent duplicate payment processing.
- Record failed payments.
- Record successful payments.
- Handle abandoned payments.
- Handle payment reversals.
- Maintain payment history.

### Security requirements

- Never store raw card numbers.
- Never store CVV values.
- Never trust frontend payment success responses.
- Verify webhook signatures.
- Implement idempotency.
- Validate payment amounts against server-side records.
- Ensure that a payment cannot activate a plan more than once.

All payment secrets must be stored in environment variables.

Provide sandbox/test mode and production mode configuration.

---

# 12. WALLET AND FINANCIAL MANAGEMENT

Implement a proper financial wallet system.

The prototype currently uses a simple JavaScript balance variable. Replace it with a secure backend-driven wallet.

### Wallet information

Each user should have:

- Wallet ID.
- User ID.
- Currency.
- Available balance.
- Pending balance.
- Total lifetime earnings.
- Total withdrawals.
- Wallet status.

### Transaction types

Support:

- Testing reward.
- Pending reward.
- Reward reversal.
- Proxy purchase.
- Withdrawal request.
- Withdrawal completed.
- Withdrawal failed.
- Manual administrative adjustment.
- Refund.

### Transaction records

Each transaction should contain:

- Transaction ID.
- User ID.
- Transaction reference.
- Transaction type.
- Amount.
- Currency.
- Previous balance.
- New balance.
- Status.
- Description.
- Related submission or payment ID.
- Creation date.

### Financial integrity

Implement a ledger-based financial architecture.

Do not allow frontend JavaScript to modify wallet balances.

Use database transactions when processing financial operations.

Ensure that simultaneous reward approvals cannot create incorrect balances.

Every wallet credit and debit must have an auditable transaction record.

Do not silently delete financial transactions.

### Withdrawal system

Implement:

- Withdrawal eligibility checks.
- Minimum withdrawal amount.
- Supported withdrawal methods.
- Bank account management.
- Withdrawal request.
- Withdrawal processing.
- Withdrawal status.
- Administrative approval.
- Provider payout integration where available.
- Withdrawal history.

Do not display a successful withdrawal when the payment provider has not confirmed it.

---

# 13. CURRENCY CONVERSION AND EXCHANGE RATES

Reproduce the currency converter shown in the prototype.

Support the following currencies initially:

- NGN – Nigerian Naira.
- USD – US Dollar.
- GBP – British Pound.
- CAD – Canadian Dollar.
- EUR – Euro.
- GHS – Ghanaian Cedi.
- KES – Kenyan Shilling.
- ZAR – South African Rand.
- INR – Indian Rupee.
- AED – UAE Dirham.

Design the system to support additional currencies.

### Requirements

1. Integrate a reliable external exchange-rate API.
2. Retrieve exchange rates from the backend.
3. Cache exchange rates.
4. Store the last successful update time.
5. Provide fallback reference rates.
6. Display whether rates are live or using fallback data.
7. Allow manual refresh.
8. Handle API failures gracefully.

Use the backend as the central exchange-rate service.

The frontend must not call an external exchange-rate provider directly using exposed credentials.

Currency conversion is for displaying equivalent values unless actual multi-currency wallet functionality is explicitly configured.

Do not change the underlying NGN wallet balance merely because the user changes the display currency.

Clearly label indicative conversion rates and their update timestamps.

---

# 14. ADMINISTRATIVE DASHBOARD

Integrate the new functionality into the existing admin dashboard.

Do not build a completely separate admin application unless the existing architecture requires it.

Create appropriate administrative sections.

### A. Overview

Display:

- Total registered testers.
- Active testers.
- Total developers.
- Total applications.
- Active campaigns.
- Total submissions.
- Pending verification.
- Approved submissions.
- Rejected submissions.
- Total rewards issued.
- Pending withdrawals.
- Completed withdrawals.
- Total proxy purchases.

### B. User management

- List users.
- Search users.
- Filter users.
- View user profiles.
- View testing history.
- View wallet history.
- Suspend accounts.
- Reactivate accounts.
- Investigate suspicious activity.

### C. Application management

- Create applications.
- Edit applications.
- Review developer submissions.
- Approve applications.
- Reject applications.
- Upload screenshots.
- Manage store links.
- Configure supported countries.

### D. Campaign management

- Create campaigns.
- Configure rewards.
- Set participant limits.
- Configure target regions.
- Assign questionnaires.
- Pause campaigns.
- Resume campaigns.
- Complete campaigns.

### E. Submission verification

Provide a dedicated verification interface.

Display:

- Tester information.
- Application information.
- Campaign information.
- Submission reference.
- Answers.
- Written feedback.
- Testing duration.
- Submission timestamp.
- Automated quality flags.
- Previous submissions where relevant.

Administrators should be able to approve, reject or request additional information.

### F. Financial management

- View all transactions.
- Review reward records.
- Review payment records.
- Process withdrawal requests.
- View proxy purchases.
- View wallet balances.
- Perform authorized manual adjustments.
- Export financial reports.

### G. Configuration

Allow authorized administrators to manage:

- Reward values.
- Proxy plans.
- Supported currencies.
- Minimum withdrawal.
- Testing limits.
- Questionnaire templates.
- Campaign settings.
- Notification settings.
- Payment configurations where supported.

All administrative actions must be permission-controlled and audited.

---

# 15. DEVELOPER DASHBOARD

Where developer accounts are supported, create a developer dashboard.

Developers should be able to:

- Submit applications.
- Manage application information.
- Upload screenshots.
- Create testing campaign requests.
- Configure testing objectives.
- Specify target devices and regions.
- Monitor campaign progress.
- View completed testing reports.
- View aggregated feedback.
- View common issues and reported safety concerns.

Developers must only access their own applications and campaigns.

Do not expose private tester information or personal data unnecessarily.

Developers must not be able to alter submitted feedback or manipulate reward decisions.

---

# 16. API DEVELOPMENT – MANDATORY

This feature MUST be API-powered.

Do not implement it as a frontend-only feature with JavaScript arrays, local storage or hardcoded records.

All important business operations must be performed through backend APIs.

Follow the existing API versioning, authentication, response formats and architectural conventions.

If the project does not already have API versioning, introduce a sensible versioned API structure.

For example:

`/api/v1/`

The following is a suggested endpoint structure. Adapt the names to the existing project architecture.

### Authentication

- POST `/api/v1/auth/register`
- POST `/api/v1/auth/login`
- POST `/api/v1/auth/logout`
- POST `/api/v1/auth/google`
- POST `/api/v1/auth/forgot-password`
- POST `/api/v1/auth/reset-password`
- GET `/api/v1/auth/me`

### Profile

- GET `/api/v1/profile`
- PUT `/api/v1/profile`
- GET `/api/v1/profile/notifications`

### Dashboard

- GET `/api/v1/tester/dashboard`
- GET `/api/v1/tester/statistics`

### Applications

- GET `/api/v1/applications`
- GET `/api/v1/applications/{id}`
- GET `/api/v1/applications/{id}/screenshots`

### Testing campaigns

- GET `/api/v1/campaigns`
- GET `/api/v1/campaigns/{id}`
- POST `/api/v1/campaigns/{id}/reserve`
- POST `/api/v1/campaigns/{id}/start`
- GET `/api/v1/campaigns/{id}/eligibility`

### Testing sessions

- POST `/api/v1/testing-sessions`
- GET `/api/v1/testing-sessions/{id}`
- POST `/api/v1/testing-sessions/{id}/complete`
- POST `/api/v1/testing-sessions/{id}/cancel`

### Questionnaires

- GET `/api/v1/campaigns/{id}/questionnaire`
- GET `/api/v1/questionnaires/{id}`

### Submissions

- POST `/api/v1/submissions`
- GET `/api/v1/submissions`
- GET `/api/v1/submissions/{id}`
- GET `/api/v1/submissions/{id}/status`

### Wallet

- GET `/api/v1/wallet`
- GET `/api/v1/wallet/balance`
- GET `/api/v1/wallet/transactions`
- GET `/api/v1/wallet/transactions/{id}`

### Withdrawals

- GET `/api/v1/withdrawals`
- POST `/api/v1/withdrawals`
- GET `/api/v1/withdrawals/{id}`
- POST `/api/v1/withdrawals/{id}/cancel`

### Payments

- POST `/api/v1/payments/initialize`
- GET `/api/v1/payments/{reference}`
- POST `/api/v1/payments/webhook`

### Proxy services

- GET `/api/v1/proxy/plans`
- POST `/api/v1/proxy/purchase`
- GET `/api/v1/proxy/sessions`
- GET `/api/v1/proxy/sessions/{id}`
- POST `/api/v1/proxy/sessions/{id}/cancel`

### Currency

- GET `/api/v1/currencies`
- GET `/api/v1/exchange-rates`
- GET `/api/v1/exchange-rates/{currency}`

### Developer APIs

- GET `/api/v1/developer/dashboard`
- POST `/api/v1/developer/applications`
- PUT `/api/v1/developer/applications/{id}`
- POST `/api/v1/developer/applications/{id}/screenshots`
- GET `/api/v1/developer/campaigns`
- POST `/api/v1/developer/campaigns`
- GET `/api/v1/developer/reports`

### Administrative APIs

Create appropriately protected endpoints for:

- Dashboard statistics.
- User management.
- Application management.
- Campaign management.
- Questionnaire management.
- Submission verification.
- Reward management.
- Withdrawal management.
- Payment management.
- Proxy plan management.
- Exchange-rate configuration.
- Platform configuration.
- Audit logs.

### API requirements

Every API must have:

- Proper request validation.
- Authentication where required.
- Authorization where required.
- Consistent response structure.
- Appropriate HTTP status codes.
- Proper exception handling.
- Pagination for listing endpoints.
- Search and filtering where appropriate.
- Rate limiting for sensitive operations.
- Database-backed data.
- Clear error messages.

Document the APIs using OpenAPI/Swagger if compatible with the project.

Include example request and response structures.

The APIs should be usable by both the existing web frontend and a future mobile application.

---

# 17. DATABASE DESIGN

Design the necessary database tables or collections based on the existing database architecture.

Potential entities include:

1. Applications.
2. Application screenshots.
3. Developer profiles.
4. Testing campaigns.
5. Campaign eligibility rules.
6. Campaign reservations.
7. Testing sessions.
8. Questionnaires.
9. Questionnaire questions.
10. Questionnaire options.
11. Testing submissions.
12. Submission answers.
13. Submission verification records.
14. User wallets.
15. Wallet transactions.
16. Reward records.
17. Payment records.
18. Withdrawal requests.
19. Bank accounts.
20. Proxy plans.
21. Proxy sessions.
22. Exchange rates.
23. Notifications.
24. Fraud and quality flags.
25. Administrative audit logs.

These are suggested entities, not instructions to create duplicate tables.

Inspect the existing schema and reuse existing models wherever possible.

### Database requirements

- Use appropriate foreign keys.
- Add indexes for frequently queried fields.
- Use unique constraints where necessary.
- Use appropriate decimal precision for monetary values.
- Use timestamps.
- Implement appropriate deletion and archival policies.
- Prevent duplicate transactions.
- Preserve financial history.
- Implement proper database transactions.

Create reversible database migrations following the project's conventions.

Do not run migrations that destroy existing data.

---

# 18. NOTIFICATIONS

Implement a notification system.

Notify users when:

- A new relevant testing assignment becomes available.
- An assignment is reserved.
- A submission is successfully received.
- A submission is approved.
- A submission is rejected.
- Additional information is requested.
- A reward is credited.
- A payment succeeds or fails.
- A proxy session is activated or expires.
- A withdrawal is requested.
- A withdrawal is completed or rejected.

Support in-app notifications.

Integrate email notifications using the existing email infrastructure if available.

Design the system to support push notifications in a future mobile application.

---

# 19. SECURITY AND FRAUD PREVENTION

Security is a mandatory part of this implementation.

Implement appropriate safeguards against:

- Unauthorized access.
- Privilege escalation.
- SQL injection.
- Cross-site scripting.
- Cross-site request forgery where applicable.
- Broken object-level authorization.
- Duplicate submissions.
- Duplicate payments.
- Reward manipulation.
- Withdrawal manipulation.
- Fake testing sessions.
- Automated spam submissions.
- Account abuse.
- Credential theft.

Additional safeguards:

1. Validate all input on the backend.
2. Use secure password hashing.
3. Protect authentication tokens and sessions.
4. Apply rate limits.
5. Validate file uploads.
6. Restrict administrative functionality.
7. Log sensitive financial actions.
8. Implement idempotency for financial operations.
9. Protect payment webhooks.
10. Prevent users from submitting for other users.
11. Prevent unauthorized reward modifications.
12. Use environment variables for sensitive credentials.

### Fraud detection

Implement a configurable risk-assessment system that can flag:

- Repeated or copied feedback.
- Unusually short testing sessions.
- Duplicate accounts where reasonably detectable.
- Repeated suspicious submissions.
- Abnormal testing patterns.
- Repeated failed verification attempts.

Use these signals for investigation rather than automatically treating them as conclusive evidence of misconduct.

Avoid collecting unnecessary personal information or intrusive device data.

---

# 20. FRONTEND IMPLEMENTATION

Use the existing frontend framework and design system.

Recreate the user experience demonstrated in `project-overview.html` while adapting it to the existing application's architecture.

The following pages should be implemented:

### Public pages

- TrustLens landing page.
- Registration.
- Login.
- Password recovery.

### Tester pages

- Tester dashboard.
- Available assignments.
- Application details.
- Testing interface.
- Questionnaire.
- Submission confirmation.
- Submission history.
- Wallet.
- Transaction history.
- Withdrawal management.
- Proxy plans.
- Proxy session information.
- Profile.
- Notifications.

### Developer pages

- Developer dashboard.
- Application management.
- Screenshot management.
- Campaign management.
- Testing reports.

### Admin pages

- Overview.
- User management.
- Application management.
- Campaign management.
- Submission verification.
- Wallet management.
- Payment management.
- Withdrawal management.
- Proxy management.
- Reports.
- Settings.

### Design requirements

Use the prototype as the visual foundation.

Preserve its:

- Clean layout.
- Card-based design.
- Red and green visual identity.
- Responsive navigation.
- Dashboard statistics.
- Application cards.
- Testing questionnaire presentation.
- Progress indicators.
- Wallet layout.
- Transaction tables.
- Modal interactions.

Improve:

- Mobile responsiveness.
- Loading states.
- Empty states.
- Form validation.
- Error messages.
- Accessibility.
- Navigation consistency.
- Confirmation dialogs.
- Data presentation.

Ensure compatibility with the existing project's styling conventions.

Do not create unnecessary visual differences between the new feature and the rest of the existing application.

---

# 21. PERFORMANCE AND SCALABILITY

The system should be designed to support increasing numbers of:

- Users.
- Applications.
- Campaigns.
- Testing submissions.
- Wallet transactions.
- Developers.
- Administrators.

Implement:

- Database pagination.
- Efficient database queries.
- Appropriate indexing.
- Caching where appropriate.
- Background jobs for time-consuming operations.
- Queue-based notifications where supported.
- Efficient image storage and delivery.
- Appropriate API response sizes.

Avoid loading every submission, user or transaction into the browser at once.

---

# 22. TESTING AND QUALITY ASSURANCE

Do not consider the implementation complete merely because the pages render.

Implement appropriate automated tests.

### Backend tests

Test:

- Registration.
- Login.
- Authentication.
- Authorization.
- Application retrieval.
- Campaign eligibility.
- Assignment reservation.
- Testing session creation.
- Questionnaire validation.
- Submission creation.
- Duplicate submission prevention.
- Submission approval.
- Submission rejection.
- Reward crediting.
- Wallet calculations.
- Payment verification.
- Webhook idempotency.
- Withdrawal processing.
- Proxy activation.
- Exchange-rate retrieval.

### Frontend tests

Test:

- Page navigation.
- Form validation.
- Dashboard data retrieval.
- Application listing.
- Questionnaire progression.
- Submission handling.
- Wallet display.
- Payment interactions.
- Responsive behavior.
- Error and loading states.

### Integration tests

Verify that:

- The frontend communicates with the backend.
- Database records are correctly created.
- Payments update the appropriate records.
- Approved submissions credit the correct wallets.
- Rejected submissions do not credit available balances.
- Duplicate requests do not create duplicate financial records.
- Notifications are generated appropriately.

Run the existing project's test suite and fix regressions introduced by your implementation.

---

# 23. ENVIRONMENT CONFIGURATION

Create or update the environment configuration based on the project's existing structure.

Potential configuration values include:

- Application base URL.
- API base URL.
- Database connection.
- Google OAuth credentials.
- Payment provider public key.
- Payment provider secret key.
- Payment webhook secret.
- Exchange-rate API key, if required.
- Proxy provider credentials.
- Email service credentials.
- File storage configuration.

Never hardcode secrets.

Update `.env.example` with the required variable names and safe placeholder values.

Do not commit real credentials.

Document the setup requirements.

---

# 24. IMPLEMENTATION ORDER

Implement the feature in a logical order.

### Phase 1: Existing project analysis

- Inspect the codebase.
- Inspect `project-overview.html`.
- Identify reusable infrastructure.
- Plan integration.
- Identify missing dependencies.

### Phase 2: Database and backend foundations

- Create or extend models.
- Create migrations.
- Implement services.
- Implement repositories where appropriate.
- Implement business logic.
- Implement authorization.
- Implement API routes.
- Implement request validation.

### Phase 3: Authentication and user management

- Registration.
- Login.
- Google authentication.
- User profiles.
- Permissions.

### Phase 4: Application and campaign management

- Applications.
- Screenshots.
- Developer management.
- Campaign creation.
- Campaign eligibility.

### Phase 5: Testing workflow

- Assignment reservations.
- Testing sessions.
- Questionnaires.
- Submission handling.
- Verification workflow.

### Phase 6: Wallet and payments

- Wallet.
- Ledger.
- Rewards.
- Payment provider.
- Withdrawals.

### Phase 7: Proxy and exchange-rate integration

- Proxy plans.
- Provider integration.
- Session management.
- Currency conversion.
- Exchange-rate caching.

### Phase 8: Frontend

- Landing page.
- Dashboard.
- Application listings.
- Testing interface.
- Wallet.
- Admin dashboard.
- Developer dashboard.

### Phase 9: Notifications and security

- Notifications.
- Fraud detection.
- Audit logs.
- Security checks.

### Phase 10: Testing and final integration

- Run migrations safely.
- Run automated tests.
- Test API integrations.
- Test complete user workflows.
- Fix errors.
- Verify existing features remain functional.

Do not skip phases simply to finish faster.

---

# 25. IMPORTANT BUSINESS LOGIC RULES

These rules must be respected throughout the implementation.

1. A user cannot access protected testing assignments without authentication.
2. A user cannot submit a testing report for an assignment they do not own.
3. A user cannot submit the same assignment multiple times unless explicitly permitted.
4. Campaign participation limits must be enforced by the backend.
5. A user cannot determine their own reward amount through frontend requests.
6. A user cannot approve their own submission.
7. A developer cannot approve their own campaign or alter tester feedback.
8. Pending rewards must not be treated as withdrawable funds.
9. Only approved and eligible submissions can generate available rewards.
10. Every reward must have a corresponding transaction record.
11. Every payment must be independently verified.
12. Every withdrawal must be validated and recorded.
13. Proxy sessions must only activate after successful payment and provider confirmation.
14. Currency conversion must not change the actual wallet balance.
15. Administrative financial adjustments must be auditable.
16. Rejected submissions must retain their records and rejection reasons.
17. Store screenshots and app descriptions must be stored and retrieved through the proper application APIs.
18. Public store ratings and reviews must not be incentivized or manipulated.
19. Testing feedback should be private, authentic and independent.
20. All sensitive operations must be performed and validated on the backend.

---

# 26. ACCEPTANCE CRITERIA

The implementation will only be considered complete when the following conditions are satisfied.

### Authentication

- A new user can register.
- A user can log in.
- Google authentication works when configured.
- Protected pages require authentication.
- User permissions are enforced.

### Applications

- Administrators can create applications.
- Applications are stored in the database.
- Developers can submit applications where enabled.
- Screenshots can be uploaded.
- Users can retrieve applications through APIs.

### Campaigns

- Administrators can create campaigns.
- Campaigns can be activated.
- Users can see eligible campaigns.
- Campaign limits are enforced.
- Campaign information is dynamically retrieved.

### Testing

- Users can start testing sessions.
- Users can view application screenshots.
- Users can answer questionnaires.
- Required questions are validated.
- Submissions are saved to the database.
- Duplicate submissions are prevented.

### Verification

- Administrators can view submissions.
- Administrators can approve submissions.
- Administrators can reject submissions.
- Rejection reasons are recorded.
- Users can see submission statuses.

### Wallet

- Approved rewards are credited correctly.
- Pending rewards are separated from available funds.
- Transactions are recorded.
- Balances are calculated correctly.
- Users can view transaction history.

### Payments

- Real payment checkout works when configured.
- Payment verification works.
- Webhooks work.
- Duplicate payment processing is prevented.
- Proxy purchases are correctly recorded.

### Currency

- Exchange rates can be retrieved.
- Rates are cached.
- Fallback rates are available.
- Conversion works correctly.
- Display currency does not alter the original wallet balance.

### Administration

- Administrators can manage applications.
- Administrators can manage campaigns.
- Administrators can verify submissions.
- Administrators can manage withdrawals.
- Administrators can view financial records.
- Administrative actions are audited.

### API

- All core functionality is accessible through APIs.
- APIs use proper authentication.
- APIs validate input.
- APIs enforce authorization.
- API documentation is available.
- APIs are usable by future mobile clients.

### Existing project

- Existing functionality continues working.
- No existing user data is lost.
- No unrelated modules are unnecessarily modified.
- Existing tests continue passing.

---

# 27. FINAL DELIVERABLES

When implementation is complete, provide a comprehensive implementation report containing:

1. Summary of the features implemented.
2. Existing architecture discovered.
3. Files created.
4. Files modified.
5. Database tables created or modified.
6. Migrations created.
7. API endpoints implemented.
8. Third-party services integrated.
9. Environment variables required.
10. Installation instructions.
11. Migration instructions.
12. Frontend setup instructions.
13. Backend setup instructions.
14. Testing commands.
15. Test results.
16. Any integrations requiring external credentials.
17. Any incomplete functionality and the reason it remains incomplete.
18. Production deployment considerations.

Also provide the API documentation location.

Clearly distinguish between:

- Fully implemented functionality.
- Functionality implemented but awaiting external credentials.
- Functionality requiring a real third-party provider.
- Functionality not implemented.

Never claim a feature works when it is only simulated.

---

# 28. FINAL INSTRUCTIONS TO THE AI AGENT

This is an existing project, and your responsibility is to integrate TrustLens into it properly.

Do not create a standalone application.

Do not merely generate frontend pages.

Do not use mock data as a substitute for backend functionality.

Do not leave buttons with placeholder actions.

Do not create fake payments, fake wallet credits, fake proxy sessions or simulated verification in production functionality.

Do not hardcode the applications, testing campaigns, rewards, users or transaction history.

Implement the necessary backend APIs, database structures, business logic, frontend interfaces and third-party integrations.

Use the existing project architecture wherever possible.

If the reference HTML contains a workflow that needs improvement, improve it without losing its intended functionality.

Prioritize:

1. Correct integration with the existing project.
2. Secure backend architecture.
3. Functional API development.
4. Database integrity.
5. Accurate financial operations.
6. Good user experience.
7. Maintainable code.
8. Automated testing.
9. Production readiness.

**Most importantly, do not stop after inspecting the project or generating an implementation plan. Proceed to implement the feature, integrate it into the existing application, run the necessary tests, fix implementation errors and provide the final implementation report.**

The final result must be a fully integrated, functional TrustLens module that works within the existing project rather than an independent prototype.
