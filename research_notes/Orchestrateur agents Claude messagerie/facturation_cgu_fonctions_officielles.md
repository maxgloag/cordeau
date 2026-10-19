# Claude Code remote/async/multi-agent features, billing and terms on Pro/Max (state as of 2026-10-04)

## 1. What changed on June 15, 2026 (Agent SDK, `claude -p`, GitHub Actions)?

### Takeaway
The June 15 split (programmatic usage moved to a separate monthly credit at API rates) was announced May 13 but PAUSED on June 15 itself and was never applied. As of the sources found, `claude -p`, Agent SDK and GitHub Actions still draw from normal subscription limits. The Aitensity README's premise ("since June 15, programmatic use is metered at full API rates") is stale/incorrect. No later reinstatement of the change was found (searches covering July-Oct 2026 returned nothing new), but Anthropic said a reworked plan will come with advance notice, so it can return.

### Cited Findings
- Official Help Center article, status block: "Claude Agent SDK, `claude -p`, and third-party app usage still draw from your subscription's usage limits"; the changes are described as paused as of June 15, 2026. — [support.claude.com: Use the Claude Agent SDK with your Claude plan](https://support.claude.com/en/articles/15036540-use-the-claude-agent-sdk-with-your-claude-plan)
- The paused plan (same article): separate per-user, non-poolable monthly credit, one-time opt-in claim, no rollover. Pro $20, Max 5x $100, Max 20x $200, Team Standard $20, Team Premium $100, Enterprise usage-based $20, Enterprise Premium $200. It covered Agent SDK projects (Python/TS), `claude -p`, Claude Code GitHub Actions, and third-party apps using Agent SDK auth. It did NOT cover interactive Claude Code in terminals/IDEs, web/desktop/mobile chat, Cowork. After credit exhaustion usage would go to API rates via usage credits, or stop. — same source
- Anthropic's wording as relayed by secondary source: the planned move "is no longer happening", plan being reworked "to better support how users build with Claude subscriptions", with advance notice before any future change. "Nothing changes for now." — [digitalapplied](https://www.digitalapplied.com/blog/anthropic-claude-credit-overhaul-june-15-2026) (secondary; quotes attributed to Help Center/email), [search summary citing DevOps.com / The New Stack](https://thenewstack.io/anthropic-pauses-claude-agent-sdk-subscription-change/) (page body not retrievable; title confirms "pauses ... on day it was due to take effect")
- Announcement date May 13, 2026 (split into two pools, effective June 15). — [VentureBeat](https://venturebeat.com/technology/anthropic-reinstates-openclaw-and-third-party-agent-usage-on-claude-subscriptions-with-a-catch) (describes the pre-pause plan; its framing that credits become "the permanent billing structure" is superseded by the pause)
- Same VentureBeat piece says May 13 also reversed an "April 2026 ban" on third-party agents like OpenClaw on subscriptions. Not independently verified here. — [VentureBeat](https://venturebeat.com/technology/anthropic-reinstates-openclaw-and-third-party-agent-usage-on-claude-subscriptions-with-a-catch)
- Pushback from developers/tool makers (Zed, Ars Technica analysts) cited as reason for the pause (press, not Anthropic). — search summary of [The Decoder](https://the-decoder.com/anthropic-backs-off-unpopular-billing-overhaul-as-price-war-with-openai-looms/), [Zed blog](https://zed.dev/blog/anthropic-subscription-changes)
- "Programmatic vs interactive" as defined in the (paused) plan: programmatic = Agent SDK, `claude -p`, GitHub Actions, third-party apps authenticating via Agent SDK; interactive = Claude Code in terminal/IDE, chat apps, Cowork. — [support.claude.com article](https://support.claude.com/en/articles/15036540-use-the-claude-agent-sdk-with-your-claude-plan)
- Official docs, still current: "Advertised usage limits for Pro and Max plans assume ordinary, individual usage of Claude Code and the Agent SDK." — [code.claude.com/docs/en/legal-and-compliance](https://code.claude.com/docs/en/legal-and-compliance)
- Official docs: GitHub Actions with a subscription OAuth token (`CLAUDE_CODE_OAUTH_TOKEN` via `claude setup-token`) "runs use your Claude subscription instead of API billing"; for org-wide shared secrets, an API key is recommended since the OAuth token is tied to one person's subscription. — [code.claude.com/docs/en/github-actions](https://code.claude.com/docs/en/github-actions)

### Inferences
- The Aitensity README's "interactive-only to dodge metering" rationale is currently unnecessary for billing, though the interactive-native design is still the safest posture against a future reinstatement.
- If the split returns, an orchestrator using `claude -p` workers would be hit first; interactive sessions driven by Channels would not (per the paused plan's scope).

### Gaps
- No Anthropic page found dated after June 2026 confirming the final status; the support article could not be checked for a "last updated" date. Verify before relying on it.
- Exact boundary for edge cases (e.g. interactive session driven via Channels vs `-p`) was only defined in the paused plan.

## 2. Pro vs Max limits, parallel consumption, monitoring, behavior at limit

### Takeaway
Usage is a rolling 5-hour session window plus a weekly window, shared across claude.ai, Claude Desktop and Claude Code; parallel sessions/subagents just burn the same pool faster. Pro $20/mo ($17 annual); Max from $100 (5x or 20x Pro per session). Exact token/message quotas are not published. At the limit you wait, switch model family, or enable pay-as-you-go usage credits (explicit opt-in).

### Cited Findings
- Pricing: Pro $17/mo annual or $20/mo, includes Claude Code; Max from $100/mo with 5x or 20x multiplier vs Pro; "Usage limits reset on a rolling five-hour session window"; "Pro gives you at least 5x more usage per 5-hour session than Free. Max gives you 5x or 20x more usage per 5-hour session than Pro." — [claude.com/pricing](https://claude.com/pricing)
- Usage across claude.ai, Claude Code, Claude Desktop counts toward the same limit; paid users can buy extra usage credits. — [support.claude.com usage and length limits](https://support.claude.com/en/articles/11647753-how-do-usage-and-length-limits-work)
- Weekly window exists: error text "You've hit your weekly limit · resets Mon 12:00am"; "You've hit your session limit · resets 3:45pm". Session and weekly limits are shared across all models; model-specific Opus/Sonnet limits exist, and switching to another model family keeps you working. — [code.claude.com/docs/en/errors](https://code.claude.com/docs/en/errors); [costs](https://code.claude.com/docs/en/costs)
- Recovery: wait for reset, switch model family, `/usage-credits` (opens Settings > Usage; Pro/Max can set a monthly spend limit). On v2.1.234+, Claude Code can wait and auto-continue after reset (`autoContinueAtUsageLimit`, `/rate-limit-options`). — [costs](https://code.claude.com/docs/en/costs), [errors](https://code.claude.com/docs/en/errors)
- Switching to metered billing needs explicit consent; `ANTHROPIC_API_KEY` in the env takes precedence over the subscription (unexpected API charges). — [support.claude.com: Using Claude Code with Pro or Max](https://support.claude.com/en/articles/11145838-using-claude-code-with-your-pro-or-max-plan)
- Monitoring: `/usage` shows plan usage bars, a 24h/7-day breakdown attributed to skills, subagents, plugins, MCP servers, heavy `/loop` tasks; figures computed from local history on that machine only (other devices/claude.ai excluded). Session `$` figure is irrelevant for subscribers. `/status` also shows the active credential. Dashboard: claude.ai/settings/usage. — [costs](https://code.claude.com/docs/en/costs)
- Cost drivers for long/parallel work: full context re-sent each request; cache lifetime 1 hour on subscription (5 min once on usage credits); each subagent/teammate sends its own requests; scheduled tasks and cross-session messages re-send full context while idle. — [costs](https://code.claude.com/docs/en/costs)
- Agent teams use "approximately 7x more tokens than standard sessions" when teammates run in plan mode; docs recommend Sonnet for teammates and small teams. — [costs](https://code.claude.com/docs/en/costs)
- Anthropic engineering: agents use ~4x tokens of chat, multi-agent systems ~15x; worth it only when task value is high. Opus lead + Sonnet subagents beat single Opus by 90.2% on their internal research eval (research use case, not coding). — [anthropic.com/engineering/multi-agent-research-system](https://www.anthropic.com/engineering/multi-agent-research-system)
- Cloud sessions: "share rate limits with all other Claude and Claude Code usage within your account. Running multiple tasks in parallel consumes more rate limits proportionately. There is no separate compute charge for the cloud VM." — [claude-code-on-the-web](https://code.claude.com/docs/en/claude-code-on-the-web)

### Inferences
- 2-3 concurrent workers on Pro will likely exhaust the 5-hour window quickly on heavy work (multiplier of 2-3x consumption minimum, plus subagent/context overhead); Max 5x ($100) is the realistic floor for sustained parallel use. This is an estimate, not an Anthropic figure.
- Running workers on Sonnet and the orchestrator light reduces burn (docs recommend Sonnet for teammates).

### Gaps
- Absolute weekly/5-hour token or message quotas for Pro/Max are not published; none found. Weekly limit introduction details not retrieved.
- Whether Opus 5.5 vs Sonnet 5.5 weights differ in the pool: only the model-specific-limit messages were found.

## 3. Official features: billing/availability on Pro

### Takeaway
All of the listed features are available on Pro and draw from the same subscription pool; none is separately metered for Pro (only overage via optional usage credits). Claude Tag (the Team/Enterprise Slack successor) is not available on Pro/Max; the older Claude Code in Slack remains the Pro/Max path. Channels and Remote Control require a local always-on process.

### Cited Findings
- **Channels (Telegram, Discord, iMessage; research preview)**: need claude.ai or Console auth; "Pro and Max users without an organization skip these checks entirely: channels are available and users opt in per session with `--channels`"; Team/Enterprise need an Owner to enable. Requires Bun. Events only arrive while the session is open: "for an always-on setup you run Claude in a background process or persistent terminal." Permission prompts pause the session unless a channel relays them or `--dangerously-skip-permissions` is used. Allowlist sender pairing (`/discord:access policy allowlist`). Only plugins on Anthropic's allowlist work in `--channels` during preview. With `-p`, channels run but tools needing terminal input are disabled. — [code.claude.com/docs/en/channels](https://code.claude.com/docs/en/channels)
- Community claim (Aitensity README, not official): `claude --channels` freezes when spawned headless by launchd (refs anthropics/claude-code#37878, #9026); tmux with a PTY and a respawn loop works; `CLAUDE_CONFIG_DIR` isolates per-agent config. I did not verify those issues. — [Aitensity/claude-agent-fleet README](https://github.com/Aitensity/claude-agent-fleet)
- **Remote Control**: available on Pro, Max, Team, Enterprise; API keys not supported; session runs locally, "your computer has to stay on and the `claude` process has to keep running". `claude remote-control` server mode with `--spawn same-dir|worktree|session` and `--capacity <N>` (default 32 concurrent sessions). Not available via Bedrock/Vertex/Foundry or custom `ANTHROPIC_BASE_URL`. — [code.claude.com/docs/en/remote-control](https://code.claude.com/docs/en/remote-control)
- **Cloud sessions (`claude --cloud`, `--remote` deprecated alias)**: "available on Pro, Max, and Team plans" (and some Enterprise seats). Runs on Anthropic VMs, GitHub-based clone/PR; parallel `--cloud` runs are independent; shares account rate limits; no extra VM charge; subagents work; agent teams opt-in via env var; `claude -p "msg" --cloud <id>` queues follow-ups. — [claude-code-on-the-web](https://code.claude.com/docs/en/claude-code-on-the-web)
- **Routines / `/schedule`** (research preview): available on Pro/Max/Team/Enterprise. Run as cloud sessions; "Routines draw down subscription usage the same way interactive sessions do." Hourly caps: 100 scheduled runs/hour/account; 30/hour per routine for Run now + API fires; API fires 100/hour/account; minimum cron interval 1 hour. Over the subscription limit: continue on metered overage only if usage credits enabled, otherwise runs rejected until reset. `/schedule` requires a claude.ai login (not API key). API trigger uses beta header `experimental-cc-routine-2026-04-01`. — [code.claude.com/docs/en/routines](https://code.claude.com/docs/en/routines)
- **Subagents / agent teams**: agent teams are experimental, off by default (`CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS=1`), interactive-only (no teammates in `-p`/Agent SDK), no hard teammate cap, docs suggest 3-5, "Start with research and review", one team per session, no nested teams, no resume of in-process teammates; teammates inherit the lead's permission mode. No plan restriction stated. — [agent-teams](https://code.claude.com/docs/en/agent-teams)
- **claude-code-action (GitHub Action)**: works with API key or subscription OAuth token (Pro/Max/Team/Enterprise); consumes GitHub Actions minutes plus subscription usage if OAuth. Under the paused plan it would have been credit-metered; currently not. — [github-actions](https://code.claude.com/docs/en/github-actions)
- **Claude in Slack**: "Claude Code in Slack" works on Pro, Max, Team, Enterprise; creates a cloud session per @mention in channels (not DMs), runs under your own account and plan limits, GitHub only. Claude Tag (shared org identity) "isn't available on individual plans"; the earlier version is being retired for Team/Enterprise. — [code.claude.com/docs/en/slack](https://code.claude.com/docs/en/slack)
- **Background agents / `/loop` / desktop scheduled tasks**: `/loop` and in-session scheduled tasks fire even while idle and resend full context (usage cost); shown in `/usage` Loops rows. — [costs](https://code.claude.com/docs/en/costs). Dedicated "background agents" page not fetched.
- Which require Max/Team: none of the above, per docs; Team/Enterprise only need admin toggles (channels, remote control, routines).

### Inferences
- Architecture "orchestrator reachable via Discord/Telegram + 2-3 workers" can be built entirely from supported pieces: Channels (or Remote Control) for the human interface, subagents/agent teams or additional interactive instances for workers; cloud sessions/Routines offload from the Mac but still count against the same pool.
- Cloud sessions avoid the always-on Mac requirement for workers but need GitHub repos (this project is on GitHub).

### Gaps
- Claude Code docs "background agents"/Workflows pages not read; `docs/en/workflows` mentioned as "dynamic workflows" in Remote Control page, details and cost (`/workflows#cost`) not fetched.
- Whether Channels messages trigger a different billing class than ordinary interactive use: nothing in the docs suggests so; not explicitly stated.

## 4. Terms of service / usage policy

### Takeaway
Consumer Terms (Free/Pro/Max) forbid access "through automated or non-human means, whether through a bot, script, or otherwise" except via API key or where Anthropic "otherwise explicitly permit[s] it"; Anthropic's Claude Code docs explicitly permit the native client and documented features (Channels, Remote Control, routines, GitHub Action with OAuth token). No clause on a maximum number of concurrent sessions was found; account sharing is forbidden. Running several always-on interactive instances under ONE account for one's own use appears consistent with the docs, but it is not stated outright.

### Cited Findings
- Consumer Terms (last updated Oct 8, 2025) — automated access: "Except when you are accessing our Services via an Anthropic API Key or where we otherwise explicitly permit it, to access the Services through automated or non-human means, whether through a bot, script, or otherwise." Account sharing: "You may not share your Account login information, Anthropic API key, or Account credentials with anyone else or make your Account available to anyone else." No explicit multiple-accounts clause found. — [anthropic.com/legal/consumer-terms](https://www.anthropic.com/legal/consumer-terms) (fetched via summarizer; verify wording against the page)
- Claude Code legal page: Pro/Max are under Consumer Terms; "Advertised usage limits for Pro and Max plans assume ordinary, individual usage of Claude Code and the Agent SDK." OAuth "is intended exclusively for purchasers of ... subscription plans and is designed to support ordinary use of Claude Code and other native Anthropic applications." Developers building products/services (including Agent SDK) "should use API key authentication"; no routing of requests through Pro/Max credentials on behalf of users; no collecting/intermediating Claude.ai credentials; enforcement "without prior notice". It also says it doesn't prevent an end user from signing in to the unmodified Claude Code binary with their own subscription. — [code.claude.com/docs/en/legal-and-compliance](https://code.claude.com/docs/en/legal-and-compliance)
- Hosting on a remote machine: the legal page's rule is about offering Claude Code in products to others; for one's own use, no prohibition of a remote host was found. The Aitensity README claim "subscription auth is meant for local, personal use" is the author's reading, not found in official text. — [Aitensity README](https://github.com/Aitensity/claude-agent-fleet) vs [legal-and-compliance](https://code.claude.com/docs/en/legal-and-compliance)
- The Aitensity README's claims checked: (a) "uses official features only (native client, Channels plugin, documented flags/env vars)" — consistent with the Channels and Remote Control docs; (b) "June 15 metered programmatic use at full API rates" — contradicted by the Help Center pause notice; (c) "No claim ... Anthropic endorses" and "not legal advice" — author's own caveats; (d) "move to API key under Commercial Terms for remote servers" — consistent with the legal page's guidance for building services, but "VPS = must use API key" is not an official rule I could find.

### Inferences
- A solo developer running his own bot-driven Claude Code instances for his own work fits "ordinary, individual usage" but the phrase is vague; 24/7 heavy parallel agents are the area where Anthropic could argue exceeding "ordinary". Risk is policy interpretation, not an explicit prohibition. Giving the Discord bot or any other person access to run prompts on his account (allowlist beyond himself) would risk the account-sharing clause.
- Orchestrator that spawns workers via `claude -p` or Agent SDK is explicitly the case Anthropic tells "developers building products" to put on API keys; for purely personal automation the text is ambiguous.

### Gaps
- No official statement found on a maximum number of simultaneous sessions/devices per Pro/Max account. Remote Control's `--capacity` default 32 suggests concurrency is technically supported.
- Consumer Terms text came through a summarizing fetch; the exact clause numbering/wording should be re-checked by a human. No Usage Policy (AUP) text read.

## 5. Official guidance on multi-agent orchestration with Claude Code

### Takeaway
Official docs frame a hierarchy: subagents (lightweight, within a session) first, agent teams (experimental) for collaboration-heavy tasks, manual parallel sessions via git worktrees, cloud sessions/projects for async fan-out; and Anthropic's engineering blog documents the orchestrator-worker pattern and its token cost.

### Cited Findings
- Agent teams page: "Before you set up a team, check whether a lighter option does the job. Subagents work within a single session, and with cross-session messaging Claude can pass findings between the sessions you run yourself." Also points to "Manual parallel sessions: Git worktrees let you run multiple Claude Code sessions yourself". — [agent-teams](https://code.claude.com/docs/en/agent-teams)
- Remote Control server mode offers `--spawn worktree` so on-demand sessions each get a git worktree and don't conflict. — [remote-control](https://code.claude.com/docs/en/remote-control)
- "Projects": a conversation where Claude starts and tracks parallel cloud sessions for repositories (page `/docs/en/claude-projects` referenced, not read). — [claude-code-on-the-web](https://code.claude.com/docs/en/claude-code-on-the-web)
- Cross-session messaging exists (`crossSessionInbound` setting, `/docs/en/cross-session-messaging`), delivered as a new turn in idle sessions and treated as untrusted-relative to the user (cannot grant permission consent). — [costs](https://code.claude.com/docs/en/costs), [agent-teams](https://code.claude.com/docs/en/agent-teams)
- Anthropic engineering blog, multi-agent research system: orchestrator-worker, ~15x chat token usage, token usage explains 80% of variance in performance. — [anthropic.com/engineering/multi-agent-research-system](https://www.anthropic.com/engineering/multi-agent-research-system)

### Inferences
- For the target design, officially recommended building blocks: an orchestrator session with Channels + subagents (cheap) for most tasks, `--spawn worktree`/git worktrees for isolating 2-3 concurrent coders, agent teams only if inter-worker discussion is needed.

### Gaps
- Pages not read: cross-session-messaging, claude-projects, workflows, sub-agents, worktrees, headless. Other Anthropic engineering posts (e.g. "Building effective agents") not consulted.
- Aitensity repo docs (architecture, pitfalls) beyond the README not read; its launchd/Channels freeze claim and the cited issue numbers are unverified.
