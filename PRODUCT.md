# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Owner controls the software and its global configuration.
- Admin manages Operators and Agents under that Admin, owns an independent business configuration and Round/result/settlement data, and views that Admin's business records.
- Operator enters sales for their assigned Agent and views only that Agent's operational records.
- The phone-only Live Dashboard is an Admin surface; an Operator on a phone sees their own operational dashboard instead.

## Product Purpose

Thai2D3D is a web application for recording and managing 2D/3D sales across daily Rounds, Agents, and Operators. Its goal is to give Admins a current view of accepted sales and settlement amounts while allowing Operators to enter sales for their assigned Agent.

## Operating Context

The application is intended to run on an Internet-accessible server and be used from different locations. Phone browsers are an important operating environment. Offline sale entry and later synchronization are available as a same-device, browser-local queue. The Operator must open the Offline sales page online once to cache it, then manually sync when connectivity returns.

## Capabilities and Constraints

- Each Admin has three daily Rounds. Schedule times and Round-specific 2D blocked-number lists are Admin-configured and isolated per Admin. The timezone is `Asia/Yangon`.
- Each Operator belongs to the Admin who created the Operator. Admins and Operators may handle multiple Agents in a Round, but each Agent can be claimed by only one account in that Round. The first Admin or Operator to open the Agent claims it for that Round; another account may claim it in a different Round.
- Admins can enter supported 2D and 3D sale inputs only for unclaimed Agents they claim or sessions already claimed by that Admin. Operators can enter sales only for their own claimed, open Agent sessions. Both can review that Agent/Round's accepted, rejected, and excluded 2D details plus separate 3D details.
- A 2D Number Limit is a Round-specific blocklist of exact values from `00` to `99`; it blocks only listed values, not sales volume. The separate 3D Number Limit is an accepted-number count cap shared across the Draw's Agents. Per-Agent/Round Amount Limits are monitoring thresholds and do not reject sales.
- Admins manage per-Round Hot Numbers and per-business configurable W/N/X lists. Other parser rule behavior is application-defined. Admins can add custom one-letter number-list codes using two-digit values; reserved codes are unavailable.
- Only accepted wagers count as income. Rejected inputs and excluded numbers do not count.
- After a Round closes, an Admin enters its 2D and 3D results. Results may be corrected; changes must be audited and settlement summaries recalculated.
- Closed-Round 2D/3D result entry, correction audit, configurable payout multipliers, and per-Agent 2D/3D winnings/commission settlement are implemented.
- Winning amounts use separate Admin-configured multipliers for 2D and 3D. Agent commissions use separate per-Agent 2D and 3D rates and are calculated against that Agent's accepted Round stakes.
- Daily, weekly, monthly, and yearly summaries include separate 2D and 3D stakes, winning stakes, winnings, commissions, and business net. Non-wager operating expenses are not in scope.
- Period summaries support Admin-wide or Operator-own scope, with Agent and Round filters plus per-Agent/per-date/per-Round breakdowns. All report periods use `Asia/Yangon` dates and weeks run Monday through Sunday.
- The Admin Live Dashboard is responsive for phones and shows open Rounds' accepted 2D/3D Number and Amount totals across that Admin's Operators, plus separate monitoring-only Amount Limits. Sale Entry provides all-Agent Round totals and a separate per-Agent statement; Admins can manage the selected Agent's Amount Limit and that Round's 2D blocklist and Hot Numbers inline.
- Offline sales are stored in IndexedDB on the current browser/device. Each sale has a client UUID for idempotent sync. The server rechecks current rules; closed Rounds, new Hot Numbers, blocked 2D values, and 3D count-limit conflicts enter an Admin review queue. Approval is an audited override that affects settlement. Sync is manual, requires returning to the app online, and is not background push or cross-device synchronization.
- Browser notifications and an audio alert are available to an Admin while the dashboard is open and the user has enabled browser permission; there is no push notification while the app is closed.
- Report visibility is confirmed for Admins across their Operators and Operators for their own Agent. Owner report visibility remains undecided.

## Brand Commitments

The product name is Thai2D3D. Users currently communicate in Burmese. The application interface language has not yet been explicitly selected.

## Evidence on Hand

The repository contains Laravel authentication and role handling, separate 2D/3D sale parsers and processors, offline synchronization/review, Round/Agent data models, and an Admin settings interface. No logo, verified financial dataset, or external result-service integration has been provided.

## Product Principles

- Keep operator sales scoped to the Operator's assigned Agent.
- Make Round, result, and settlement records auditable.
- Treat rejected and excluded wagers consistently; only accepted wager amounts affect financial totals.
- Make the Admin's current Round totals quickly scannable on a phone.
- Do not describe offline capture as a substitute for a backup: local browser storage can be cleared or lost with the device, and each queued sale must be reviewed for its sync outcome.

## Accessibility & Inclusion

The application must remain usable from phone browsers. Additional accessibility standards have not yet been specified.
