# ADR 0003: No analytics and no cookie banner

- Status: Accepted
- Date: 2026-11-26

## Context

The site targets French and European visitors. Under article 82 of the French
Data Protection Act (loi Informatique et Libertés, transposing article 5(3) of
the ePrivacy directive) and the CNIL guidelines on cookies and other trackers
(deliberations 2020-091 and 2020-092), reading or writing information on a
visitor's device requires prior consent, except when it is strictly necessary
to provide a service the visitor explicitly asked for. Consent means a banner,
a consent record and a way to withdraw it, and analytics or embeds also bring
data transfers to third parties.

## Decision

- No third-party analytics, advertising, social widgets, external fonts,
  scripts or embeds. The theme loads nothing from another origin and ships no
  JavaScript.
- No cookie banner, because the site sets no cookie that needs consent:
  - anonymous pages set no cookie at all (no `Set-Cookie` header; checked with
    `curl -I` on every page type);
  - a session cookie can be set after a contact form submission, to show the
    confirmation message, and when logging in to the admin area: both are
    strictly necessary for a service the visitor requested, hence exempt;
  - the light/dark theme follows the operating system setting and stores
    nothing in the browser.
- Spam protection uses Honeypot (hidden field, time limit stored server side),
  not a third-party CAPTCHA.
- The privacy policy (`content/*/pages/*privacy*`) states all of this.

## Consequences

- No consent management to build, test or keep compliant; nothing slows
  pages down or leaks visitor data to third parties.
- No audience measurement. If it becomes necessary, prefer server-side log
  analysis, or a tool configured within the CNIL consent exemption
  (first-party, anonymised, no cross-site tracking), in a new ADR.
- **Re-evaluate this ADR before adding** any embed (video, map, social post),
  chat widget, analytics, A/B testing, or an AI assistant that keeps data in
  the browser: each can create a cookie or tracker that needs consent.

## Alternatives

- **Analytics with a consent banner:** consent rates make the data partial,
  and the banner degrades the first visit for every visitor.
- **Consent-exempt analytics tool:** compliant if configured strictly, but
  not needed for a portfolio at launch.
