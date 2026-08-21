# Managed Website Chat — UAT Addendum

This addendum records the client-requested expansion from a static FAQ popup to a stored, two-way website chat. It must be approved with the main UAT before production launch because submitted messages and contact details are personal data.

## Included scope

- A floating, keyboard-accessible chat window on normal public pages.
- English and Bangla suggested questions with administrator-controlled plain-text answers, ordering, visibility, welcome text, and an optional short privacy note.
- Suggested-question clicks show the approved answer immediately without creating a conversation; only an anonymous aggregate click count is retained.
- A guest who types a custom question sees the contact form only after choosing to send it, with the question preserved and editable; name plus email or phone is then required.
- Approved signed-in members submit custom questions with their server-side account identity and do not see the guest contact form.
- Verified member attribution from the authenticated server session; guest-supplied details are visibly labelled unverified.
- Custom questions never receive an automatic bot answer; they enter the staff inbox in Waiting status.
- A dedicated-permission admin inbox showing who submitted each question, the source path, language, status, and transcript.
- Administrator replies, status management, unread indicators, search, pagination, and audit events for transcript views/replies/status changes.
- Eight-second polling while the visitor has the chat open. This is not advertised as real-time live-agent availability.

## Security and privacy boundaries

- Plain text only; no HTML rendering, file attachments, payment data, passwords, NID, medical records, emergency reports, or safeguarding reports.
- CSRF-protected writes, stacked per-identity and per-IP throttles, session-isolated guest transcripts, approved-member checks, soft-deleted FAQ records, and private/no-store responses.
- No IP address, user agent, query string, transcript, email, or phone number is written to application logs or audit records.
- Only a dedicated Website Chat permission opens the inbox. Viewing, replying, changing status, changing settings, and managing FAQs use separate capabilities.

## Client acceptance checks

1. Open the public homepage and confirm the Chat with us button is visible and usable with keyboard and mobile layouts.
2. Open the widget and confirm only active questions for the selected website language appear in the configured order.
3. Choose a saved question and confirm its approved answer appears immediately, no conversation is created, and its anonymous click count increases.
4. Type a custom question as a guest and press Send. Confirm the bot does not answer and the name/email/phone/question form appears with the question prefilled.
5. Submit the guest form and confirm name plus either email or phone is required, then confirm the exact question enters the admin inbox in Waiting status.
6. Submit as an approved signed-in member and confirm the guest form is skipped and the inbox identifies the verified member without trusting posted identity fields.
7. In Admin → Website Chat, find the exact visitor, question, source page, language, time, and Waiting status.
8. Reply from the admin transcript and confirm the same visitor sees the reply after polling, while another browser session receives 404 for that transcript.
9. Confirm a restricted administrator receives 403 for inbox/transcript/settings/FAQ routes that were not assigned.
10. Confirm closing a conversation prevents further messages and the public widget allows a new enquiry.

## Owner decisions required before production

- Approve the final privacy wording.
- Define the retention duration, deletion/anonymization process, legal hold, user access/export process, and encrypted-backup treatment for chat records.
- Assign which administrator roles may view transcripts and which may only manage public questions and answers.
- Approve the staff response-time wording. The interface must not claim live availability unless a staffed service-level commitment exists.
