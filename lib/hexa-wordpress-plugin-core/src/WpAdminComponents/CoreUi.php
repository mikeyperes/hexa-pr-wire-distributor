<?php

namespace Hexa\PluginCore\WpAdminComponents;

final class CoreUi {
    public static function render_assets(): void {
        static $rendered = false;

        if ( $rendered ) {
            return;
        }

        $rendered = true;
        ?>
        <style>
            .hpc-ui{--hpc-bg:#f5f7fb;--hpc-ink:#172033;--hpc-muted:#65758b;--hpc-panel:#fff;--hpc-line:#d9e0ea;--hpc-blue:#3157d5;--hpc-green:#16803c;--hpc-red:#b42336;--hpc-amber:#9a6700;--hpc-dark:#111827;--hpc-radius:8px;color:var(--hpc-ink)}
            .hpc-ui *{box-sizing:border-box}
            .hpc-shell{background:var(--hpc-bg);border:1px solid var(--hpc-line);border-radius:8px;padding:18px}
            .hpc-hero{background:linear-gradient(135deg,#101827,#1a2740);border-radius:8px;color:#f8fafc;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;margin:0 0 18px;padding:22px}
            .hpc-hero h2{color:#fff;font-size:26px;line-height:1.15;margin:0 0 8px}
            .hpc-hero p{color:#d7e1ef;font-size:14px;line-height:1.6;margin:0}
            .hpc-grid{display:grid;gap:14px;grid-template-columns:repeat(3,minmax(0,1fr))}
            .hpc-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}
            .hpc-card,.hpc-subcard{background:var(--hpc-panel);border:1px solid var(--hpc-line);border-radius:8px;padding:16px}
            .hpc-card h3,.hpc-subcard h4{font-size:15px;margin:0 0 8px}
            .hpc-card p,.hpc-subcard p{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 10px}
            .hpc-subcard{background:#fbfcfe}
            .hpc-detail-card{background:#fbfcfe;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;overflow:hidden}
            .hpc-detail-card summary{align-items:center;cursor:pointer;display:flex;font-size:13px;font-weight:800;gap:10px;justify-content:space-between;list-style:none;padding:12px 14px}
            .hpc-detail-card summary::-webkit-details-marker{display:none}
            .hpc-detail-card-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-detail-card-side{align-items:center;display:inline-flex;flex:0 0 auto;gap:8px;margin-left:auto}
            .hpc-detail-card-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:24px;justify-content:center;width:24px}
            .hpc-detail-card-toggle svg{display:block;fill:currentColor;height:10px;transform:rotate(180deg);transition:transform .18s;width:10px}
            .hpc-detail-card:not([open]) .hpc-detail-card-toggle svg{transform:rotate(0deg)}
            .hpc-detail-card-body{border-top:1px solid var(--hpc-line);padding:14px}
            .hpc-detail-card.subtle{background:transparent;border:0;border-top:1px solid #edf1f6;border-radius:0;margin:-2px 0 8px}
            .hpc-detail-card.subtle summary{color:var(--hpc-muted);font-size:12px;font-weight:700;padding:7px 2px}
            .hpc-detail-card.subtle .hpc-detail-card-body{border-top:0;color:var(--hpc-muted);padding:0 2px 8px 18px}
            .hpc-detail-card.subtle .hpc-detail-card-toggle{background:transparent;border:0;color:#8a98aa;height:18px;width:18px}
            .hpc-detail-card.subtle .hpc-detail-card-toggle svg{height:9px;width:9px}
            .hpc-detail-card.subtle .hpc-pill{font-size:11px;padding:3px 7px}
            .hpc-pill{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:#2944ad;display:inline-flex;font-size:12px;font-weight:700;gap:6px;line-height:1;padding:7px 10px}
            .hpc-pill.success{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            .hpc-pill.warning{background:#fff7e0;border-color:#f5df9c;color:var(--hpc-amber)}
            .hpc-pill.danger{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            .hpc-pill.dark{background:#202b3d;border-color:#3a4a62;color:#dbe7f3}
            .hpc-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px}
            .hpc-button{background:var(--hpc-blue);border:1px solid var(--hpc-blue);border-radius:6px;color:#fff;cursor:pointer;display:inline-flex;font-weight:700;line-height:1;padding:10px 13px;text-decoration:none}
            .hpc-button.secondary{background:#fff;color:var(--hpc-blue)}
            .hpc-button.danger{background:var(--hpc-red);border-color:var(--hpc-red)}
            .hpc-core-tabs{align-items:center;border-bottom:1px solid var(--hpc-line);display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;padding-bottom:12px}
            .hpc-core-tab{background:#fff;border:1px solid #cfd8e3;border-radius:6px;color:#253650;cursor:pointer;font-weight:800;padding:9px 12px}
            .hpc-core-tab.active{background:var(--hpc-blue);border-color:var(--hpc-blue);color:#fff}
            .hpc-core-pane{display:none}
            .hpc-core-pane.active{display:block}
            .hpc-host-tabs-shell{margin:16px 0 0}
            .hpc-host-tabs{align-items:flex-end;border-bottom:1px solid var(--hpc-line);display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 0}
            .hpc-host-tab{background:#fff;border:1px solid var(--hpc-line);border-bottom:0;border-radius:8px 8px 0 0;color:#253650;display:inline-flex;font-weight:800;line-height:1;padding:12px 16px;text-decoration:none}
            .hpc-host-tab:hover{background:#f8fbff;color:var(--hpc-blue)}
            .hpc-host-tab.active{background:#fff;box-shadow:inset 0 3px 0 var(--hpc-blue);color:var(--hpc-blue)}
            .hpc-host-tab-status{align-items:center;color:var(--hpc-muted);display:flex;font-size:12px;gap:6px;min-height:28px;padding:7px 0 0}
            .hpc-host-tab-status .spinner{float:none;margin:0}
            .hpc-host-tab-panel{display:block;position:relative}
            .hpc-host-tab-panel.is-loading{opacity:.58;pointer-events:none}
            .hpc-section{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;margin:0 0 14px;overflow:hidden}
            .hpc-section summary{align-items:center;cursor:pointer;display:flex;font-size:15px;font-weight:800;gap:12px;justify-content:space-between;list-style:none;padding:15px 16px}
            .hpc-section summary::-webkit-details-marker{display:none}
            .hpc-section-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-section-summary-side{align-items:center;display:inline-flex;flex:0 0 auto;gap:10px;margin-left:auto}
            .hpc-section-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:28px;justify-content:center;transition:background .18s,border-color .18s,color .18s;width:28px}
            .hpc-section-toggle svg{display:block;fill:currentColor;height:12px;transform:rotate(180deg);transition:transform .18s;width:12px}
            .hpc-section:not([open]) .hpc-section-toggle svg{transform:rotate(0deg)}
            .hpc-section summary:hover .hpc-section-toggle{background:#e4ebff;border-color:#c7d4ff}
            .hpc-section summary:focus-visible{box-shadow:inset 0 0 0 2px var(--hpc-blue);outline:0}
            .hpc-section-body{border-top:1px solid var(--hpc-line);padding:16px}
            .hpc-callout{background:#f8fbff;border:1px solid #cfe0ff;border-left:4px solid var(--hpc-blue);border-radius:8px;color:#253650;padding:12px 14px}
            .hpc-field{display:block;margin:0 0 12px}
            .hpc-field span{color:#314056;display:block;font-size:12px;font-weight:800;margin:0 0 6px;text-transform:uppercase}
            .hpc-field input,.hpc-field select,.hpc-field textarea{background:#fff;border:1px solid #a9b4c3;border-radius:6px;color:#172033;font-size:14px;min-height:40px;padding:8px 10px;width:100%}
            .hpc-small{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:8px 0 0}
            .hpc-steps{margin:8px 0 0 20px}
            .hpc-steps li{margin:5px 0}
            .hpc-credential-demo{display:grid;gap:12px}
            .hpc-credential-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}
            .hpc-credential-head h4{font-size:15px;margin:0 0 6px}
            .hpc-credential-head p{color:var(--hpc-muted);font-size:13px;margin:0}
            .hpc-smart-search{position:relative}
            .hpc-smart-search-status{color:var(--hpc-muted);font-size:12px;margin-top:6px}
            .hpc-smart-search-results{background:#fff;border:1px solid #d3dce8;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.12);left:0;margin-top:6px;max-height:260px;overflow:auto;position:absolute;right:0;z-index:30}
            .hpc-smart-search-result{background:#fff;border:0;border-bottom:1px solid #edf1f6;color:#172033;cursor:pointer;display:grid;gap:2px;grid-template-columns:minmax(0,1fr) auto;padding:10px 12px;text-align:left;width:100%}
            .hpc-smart-search-result:hover,.hpc-smart-search-result.active{background:#eef4ff}
            .hpc-smart-search-result strong{font-size:13px;grid-column:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .hpc-smart-search-result span{color:var(--hpc-muted);font-size:12px;grid-column:1}
            .hpc-smart-search-result em{color:#8a98aa;font-size:11px;font-style:normal;grid-column:2;grid-row:1 / span 2}
            .hpc-smart-search-selected{align-items:center;background:#eefbf3;border:1px solid #c7eed5;border-radius:8px;display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;padding:8px 10px}
            .hpc-smart-search-selected strong{font-size:13px}
            .hpc-smart-search-selected span:last-child{color:var(--hpc-muted);font-size:12px}
            .hpc-tooltip{align-items:center;background:#edf2f7;border:1px solid #cfdae7;border-radius:999px;color:#334155;cursor:help;display:inline-flex;font-size:11px;font-weight:800;height:20px;justify-content:center;position:relative;width:20px}
            .hpc-tooltip:focus{outline:2px solid var(--hpc-blue);outline-offset:2px}
            .hpc-tooltip[data-tooltip]:hover:after,.hpc-tooltip[data-tooltip]:focus:after{background:#111827;border-radius:6px;bottom:calc(100% + 8px);color:#fff;content:attr(data-tooltip);font-size:12px;font-weight:500;left:50%;line-height:1.4;max-width:280px;min-width:180px;padding:8px 10px;position:absolute;transform:translateX(-50%);white-space:normal;z-index:20}
            .hpc-toggle-list{display:grid;gap:10px;margin:10px 0}
            .hpc-toggle-row{align-items:flex-start;background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:flex;gap:10px;padding:10px 12px}
            .hpc-toggle{align-items:center;cursor:pointer;display:inline-flex;gap:9px;font-weight:800;line-height:1.3}
            .hpc-toggle input{clip:rect(0 0 0 0);height:1px;margin:-1px;opacity:0;overflow:hidden;position:absolute;width:1px}
            .hpc-toggle-ui{background:#cbd5e1;border-radius:999px;display:inline-block;flex:0 0 auto;height:22px;position:relative;width:40px}
            .hpc-toggle-ui:before{background:#fff;border-radius:999px;content:"";height:16px;left:3px;position:absolute;top:3px;transition:.18s;width:16px}
            .hpc-toggle input:checked+.hpc-toggle-ui{background:var(--hpc-blue)}
            .hpc-toggle input:checked+.hpc-toggle-ui:before{transform:translateX(18px)}
            .hpc-toggle input:focus+.hpc-toggle-ui{outline:2px solid var(--hpc-blue);outline-offset:2px}
            .hpc-toggle-label{align-items:center;display:inline-flex;gap:6px}
            .hpc-inline-details{border:0;margin:6px 0 0 0}
            .hpc-inline-details summary{align-items:center;color:var(--hpc-muted);cursor:pointer;display:inline-flex;font-size:12px;font-weight:800;gap:5px;list-style:none}
            .hpc-inline-details summary::-webkit-details-marker{display:none}
            .hpc-inline-details summary:before{content:"+";font-weight:900;line-height:1}
            .hpc-inline-details[open] summary:before{content:"-"}
            .hpc-inline-details-body{color:#3f4d63;font-size:12px;line-height:1.5;margin:6px 0 0;padding-left:15px}
            .hpc-actions.hpc-actions-bottom{border-top:1px solid var(--hpc-line);margin-top:12px;padding-top:12px}
            .hpc-external:after{content:"\2197";display:inline-block;font-size:.8em;font-weight:900;line-height:1;margin-left:5px;text-decoration:none;transform:translateY(-1px)}
            .hpc-path,.hpc-code{background:#eef0f2;border-radius:5px;color:#2f3a4a;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;padding:2px 5px;word-break:break-all}
            .hpc-readme{background:#0f1720;border-radius:8px;color:#dbe7f3;max-height:380px;overflow:auto;padding:14px;white-space:pre-wrap}
            .hpc-list{margin:0 0 0 20px}
            .hpc-list li{margin:6px 0}
            @media(max-width:1000px){.hpc-hero,.hpc-grid,.hpc-grid.two{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            if (window.hexaPluginCoreUiReady) return;
            window.hexaPluginCoreUiReady = true;
            function initPersistentDetails(scope) {
                scope = scope || document;
                var items = scope.querySelectorAll ? scope.querySelectorAll('details[data-hpc-persist-key]') : [];
                items.forEach(function(item) {
                    if (item.dataset.hpcPersistentReady === '1') return;
                    item.dataset.hpcPersistentReady = '1';
                    try {
                        var stored = window.localStorage ? window.localStorage.getItem('hpc-details-' + item.dataset.hpcPersistKey) : null;
                        if (stored === '1') item.open = true;
                        if (stored === '0') item.open = false;
                    } catch (e) {}
                });
            }
            window.hexaPluginCoreInitPersistentDetails = initPersistentDetails;
            initPersistentDetails(document);
            document.addEventListener('toggle', function(event) {
                var details = event.target;
                if (!details || !details.matches || !details.matches('details[data-hpc-persist-key]')) return;
                try {
                    if (window.localStorage) window.localStorage.setItem('hpc-details-' + details.dataset.hpcPersistKey, details.open ? '1' : '0');
                } catch (e) {}
            }, true);
            document.addEventListener('click', function(event) {
                var trigger = event.target.closest('[data-hpc-copy]');
                if (!trigger || !navigator.clipboard) return;
                navigator.clipboard.writeText(trigger.getAttribute('data-hpc-copy') || '').then(function(){
                    var old = trigger.textContent;
                    trigger.textContent = 'Copied';
                    window.setTimeout(function(){ trigger.textContent = old; }, 1200);
                });
            });
        })();
        </script>
        <?php
    }

    public static function tooltip( string $text, string $label = '?' ): string {
        return '<span class="hpc-tooltip" tabindex="0" data-tooltip="' . esc_attr( $text ) . '">' . esc_html( $label ) . '</span>';
    }

    public static function toggle( string $name, bool $checked, string $label, array $args = [] ): string {
        $id           = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : sanitize_key( $name . '-' . md5( $label ) );
        $value        = isset( $args['value'] ) ? (string) $args['value'] : '1';
        $class        = trim( 'hpc-toggle ' . sanitize_html_class( (string) ( $args['class'] ?? '' ) ) );
        $disabled     = ! empty( $args['disabled'] ) ? ' disabled' : '';
        $tooltip      = '' !== (string) ( $args['tooltip'] ?? '' ) ? ' ' . self::tooltip( (string) $args['tooltip'] ) : '';
        $checked_attr = $checked ? ' checked' : '';

        return '<label class="' . esc_attr( $class ) . '" for="' . esc_attr( $id ) . '">'
            . '<input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $checked_attr . $disabled . '>'
            . '<span class="hpc-toggle-ui" aria-hidden="true"></span>'
            . '<span class="hpc-toggle-label">' . esc_html( $label ) . $tooltip . '</span>'
            . '</label>';
    }

    public static function inline_details( string $summary, string $body_html, bool $open = false ): string {
        return '<details class="hpc-inline-details"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html( $summary ) . '</summary><div class="hpc-inline-details-body">' . wp_kses_post( $body_html ) . '</div></details>';
    }

    public static function external_link( string $url, string $label, string $class = 'hpc-button secondary' ): string {
        return '<a class="' . esc_attr( trim( $class . ' hpc-external' ) ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
    }

    public static function pill( string $text, string $tone = '' ): string {
        $class = trim( 'hpc-pill ' . sanitize_html_class( $tone ) );

        return '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
    }

    public static function card( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $meta  = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';

        return '<article class="hpc-card">'
            . ( '' !== $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' )
            . $body
            . $meta
            . '</article>';
    }

    public static function subcard( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';

        return '<article class="hpc-subcard">'
            . ( '' !== $title ? '<h4>' . esc_html( $title ) . '</h4>' : '' )
            . $body
            . '</article>';
    }

    public static function detail_card( array $args ): string {
        $title       = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body        = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $open        = ! empty( $args['open'] ) ? ' open' : '';
        $meta        = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';
        $persist_key = isset( $args['persist_key'] ) ? (string) $args['persist_key'] : '';
        $persist     = '' !== $persist_key ? ' data-hpc-persist-key="' . esc_attr( $persist_key ) . '"' : '';
        $classes     = [ 'hpc-detail-card' ];

        foreach ( [ $args['variant'] ?? '', $args['class'] ?? '' ] as $class_string ) {
            foreach ( preg_split( '/\s+/', (string) $class_string ) ?: [] as $class_name ) {
                $class_name = sanitize_html_class( $class_name );
                if ( '' !== $class_name ) {
                    $classes[] = $class_name;
                }
            }
        }

        $toggle = '<span class="hpc-detail-card-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>';

        return '<details class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . $open . $persist . '><summary><span class="hpc-detail-card-title">' . esc_html( $title ) . '</span><span class="hpc-detail-card-side">' . $meta . $toggle . '</span></summary><div class="hpc-detail-card-body">' . $body . '</div></details>';
    }

    public static function collapsible( array $args ): string {
        $title = isset( $args['title'] ) ? (string) $args['title'] : '';
        $body  = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
        $open        = ! empty( $args['open'] ) ? ' open' : '';
        $meta        = isset( $args['meta_html'] ) ? (string) $args['meta_html'] : '';
        $persist_key = isset( $args['persist_key'] ) ? (string) $args['persist_key'] : '';
        $persist     = '' !== $persist_key ? ' data-hpc-persist-key="' . esc_attr( $persist_key ) . '"' : '';
        $toggle      = '<span class="hpc-section-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>';

        return '<details class="hpc-section"' . $open . $persist . '><summary><span class="hpc-section-title">' . esc_html( $title ) . '</span><span class="hpc-section-summary-side">' . $meta . $toggle . '</span></summary><div class="hpc-section-body">' . $body . '</div></details>';
    }

    public static function copy_button( string $value, string $label = 'Copy' ): string {
        return '<button type="button" class="hpc-button secondary" data-hpc-copy="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
    }
}
