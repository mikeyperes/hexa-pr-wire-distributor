# Hexa PR Wire Distributor Architecture Audit

Audit date: 2026-07-13
Target: mashviral.com
Plugin baseline: 2.4.10
Shared Hexa WordPress Plugin Core: 0.19.39

## Scope

This audit covered the complete distributor plugin tree, live Code Snippets behavior, the MashViral Echo RSS destination rule, the working HerForward importer rule, the FIFU editor and image paths, frontend loop exclusions, admin actions, and the shared Hexa Core boundary.

The rollout intentionally avoids a high-risk big-bang rewrite. Production behavior is moved behind isolated services first, with compatibility callbacks retained only where saved WordPress options depend on their names.

## Findings Resolved In 2.5.0

1. Code Snippets 14 through 19 owned production behavior that belonged to the plugin.
   - Going Live, FIFU postbox behavior, loop exclusion, Elementor exclusion, and external image sizing now have plugin-owned modules.
   - The database snippets can be disabled after the release is deployed and browser-verified.

2. Press release exclusion had two competing implementations.
   - Query behavior now belongs to `Content\PressReleaseLoopExclusion`.
   - The old snippet PHP file contains only compatibility callbacks for saved Content Rules options.
   - Main queries, Elementor queries, SQL fallback filtering, and result fallback filtering share one contract.

3. User provisioning had conflicting implementations.
   - `Setup\HexaPrWireAuthor` is the sole canonical provisioner.
   - It enforces login `hexaprwire`, email `info@hexaprwire.com`, the author role, profile metadata, social URLs, and a valid local avatar attachment.
   - The old helper that could create an administrator was replaced by a compatibility adapter.
   - The obsolete AJAX implementation that generated `hexaprwire@<destination>` was removed.

4. Echo RSS configuration used unexplained numeric indexes in the UI class.
   - `Import\EchoRuleContract` now owns rule detection, index constants, mapping validation, application, and inspection.
   - The destination publication URL is deliberately preserved.
   - The exact HerForward rule was exported separately as a server artifact.

5. FIFU behavior was unconditional and mixed PHP, CSS, and JavaScript.
   - `Admin\FifuPostboxToggle` now loads only when hide is off and collapse is on.
   - CSS and JavaScript are separate assets.
   - The postbox starts collapsed but remains keyboard and pointer expandable.

6. External image dimensions were implemented as database snippet repairs.
   - `Media\ExternalImageSizing` owns attachment detection, safe remote probing, bounded caching, metadata repair, and aspect-preserving image tuples.
   - Remote requests use `wp_safe_remote_get`.
   - Square size requests preserve the source aspect ratio.

7. The dashboard rendered every tab and hid inactive content in the browser.
   - Only the requested tab is rendered.
   - Routes are grouped as Overview, Going Live, Import & Sync, Content Rules, Editor UI, and Diagnostics.
   - Legacy diagnostic routes resolve to Diagnostics.

8. Diagnostics and admin actions contained dead or over-generic code.
   - Hundreds of unreachable lines after an early return were removed.
   - An unrelated plugin inventory and its unguarded AJAX callback were removed.
   - Generic arbitrary function execution and unused wp-config mutation endpoints were removed.
   - Snippet toggles now accept only registered snippet IDs.
   - Four empty or unreferenced root files were removed.

## Current Ownership

- `src/Plugin.php`: composition root and module registration.
- `src/Admin`: distributor-specific admin views and editor behavior.
- `src/Content`: frontend query and content visibility policy.
- `src/Import`: Echo RSS rule contract.
- `src/Media`: external featured-image metadata and sizing.
- `src/Setup`: destination identity provisioning.
- `lib/hexa-wordpress-plugin-core`: shared, versioned infrastructure only.
- Root PHP files: legacy compatibility and larger domains awaiting staged extraction.

Classes under `src` are loaded through the plugin namespace-to-path autoloader. New business behavior should not be added as root procedural code.

## Core Boundary

A feature belongs in Hexa Core only when it is infrastructure that is reusable without Hexa PR Wire domain knowledge. Generic AJAX guards, tab rendering, checklist rendering, cleanup registries, and updater infrastructure belong in Core.

Echo rule indexes, press-release visibility, the Hexa PR Wire author profile, Force Sync semantics, and FIFU behavior for imported press releases belong in this plugin.

No Hexa Core source file changed during this release. This avoids conflicts with SMP Publication Integration and other plugins that bundle the same package.

## Remaining Staged Restructure

The following work should be released in separate, behavior-preserving checkpoints:

1. Split `force-syndication.php` into a REST controller, request authenticator, sync coordinator, source matcher, slug repair service, and response DTO.
2. Split `settings-event-handling.php` into narrow admin controllers and replace remaining inline dashboard scripts with versioned assets.
3. Move SEO registration, settings, and frontend output into `src/Seo` classes while preserving stored option names and hooks.
4. Move ACF registration into `src/Fields` definitions and keep field keys stable.
5. Replace remaining root snippet activation functions with typed feature definitions while preserving existing option IDs.
6. Add WordPress integration tests for REST authentication, Echo mutation persistence, imported attachment repair, and admin AJAX capabilities.

These extractions should not be combined with the current live behavior migration. Force Sync and SEO have a larger blast radius and require their own production fixtures and rollback points.

## Verification Gates

Every future extraction must pass:

- PHP lint for the complete plugin and bundled Core.
- Focused unit tests for the extracted contract.
- Bundled Hexa Core tests.
- Live/source file parity.
- Exact wp-admin route tests on server 236.
- Frontend home, author, category, tag, related-loop, and direct press-release checks.
- FIFU expand/collapse interaction on a real imported post.
- Representative external image width and height verification.
- PHP error-log review after each rollout.

## SMP Publication Integration

SMP Publication Integration was audited separately on MashViral and released as 0.6.183 at commit `1798374`. Its source and live trees match, five SMP tests and six bundled Core tests pass, and category/tag breadcrumb browser proof passed without console or PHP errors.

The distributor release does not change SMP or shared Core. Cross-plugin verification must still run after deployment because both plugins load Hexa Core 0.19.39.
