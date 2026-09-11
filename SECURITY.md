# Security policy

## Reporting a vulnerability

**Please do not report security vulnerabilities in public issues.**

Report them privately through GitHub instead: open the
[Security tab](https://github.com/MukurtuCMS/Mukurtu-CMS/security/advisories/new)
and choose "Report a vulnerability". That creates a private thread visible only
to you and the Mukurtu maintainers.

If you cannot use GitHub, email [support@mukurtu.org](mailto:support@mukurtu.org)
and say that your message concerns a security issue.

We do not commit to a fixed response time, but reports are taken seriously and
we will keep you informed of what we find and what we intend to do about it.

### What to include

Whatever you have. These help most:

- What the problem is, and what an attacker could do with it.
- The steps to reproduce it, or a proof of concept.
- The Mukurtu version, from the "Mukurtu Version" item on the Mukurtu Dashboard.
- Whether the issue needs an account, and if so what role.

### Please avoid

- Testing against a site you do not own or have written permission to test.
- Accessing, downloading or modifying other people's content, particularly
  content held under a cultural protocol.
- Denial of service testing, or anything that risks the availability or
  integrity of a live community's records.

## Supported versions

Security fixes are made against the most recent 4.0.x release. Sites on older
releases should update before reporting, since the issue may already be fixed.

## Scope

Mukurtu CMS is a Drupal distribution, so a report may turn out to belong to
Drupal core or to a contributed module rather than to Mukurtu itself. If it does,
we will say so and point you at the right project's disclosure process, since
those maintainers are the ones who can fix it.

## A note on cultural protocols

Mukurtu exists to let communities control access to their own cultural heritage
according to their own protocols. A flaw that exposes protocol-restricted content
to someone who should not see it is not merely an access control bug in the
ordinary sense: it can cause real harm to a community, and it is the kind of
report we most want to hear about quickly.
