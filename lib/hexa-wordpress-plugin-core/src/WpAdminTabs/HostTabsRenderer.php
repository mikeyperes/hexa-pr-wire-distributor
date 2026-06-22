<?php

namespace Hexa\PluginCore\WpAdminTabs;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class HostTabsRenderer {
    public function render( array $args ): void {
        CoreUi::render_assets();

        $tabs      = isset( $args["tabs"] ) && is_array( $args["tabs"] ) ? $args["tabs"] : [];
        $active    = isset( $args["active"] ) ? sanitize_key( (string) $args["active"] ) : "";
        $page_url  = isset( $args["page_url"] ) ? (string) $args["page_url"] : "";
        $ajax_url  = isset( $args["ajax_url"] ) ? (string) $args["ajax_url"] : ( function_exists( "admin_url" ) ? admin_url( "admin-ajax.php" ) : "" );
        $action    = isset( $args["ajax_action"] ) ? sanitize_key( (string) $args["ajax_action"] ) : "";
        $nonce     = isset( $args["nonce"] ) ? (string) $args["nonce"] : "";
        $nonce_key = isset( $args["nonce_field"] ) ? sanitize_key( (string) $args["nonce_field"] ) : "nonce";
        $root_id   = isset( $args["root_id"] ) ? sanitize_html_class( (string) $args["root_id"] ) : "hpc-host-tabs";
        $panel_id  = isset( $args["panel_id"] ) ? sanitize_html_class( (string) $args["panel_id"] ) : "hpc-host-tab-panel";
        $label     = isset( $args["label"] ) ? (string) $args["label"] : "Plugin sections";
        $callback  = $args["render_callback"] ?? null;

        if ( "" === $active || ! array_key_exists( $active, $tabs ) ) {
            $keys   = array_keys( $tabs );
            $active = isset( $keys[0] ) ? (string) $keys[0] : "";
        }

        $panel_html = "";
        if ( is_callable( $callback ) && "" !== $active ) {
            ob_start();
            call_user_func( $callback, $active );
            $panel_html = (string) ob_get_clean();
        }
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-host-tabs-shell" data-hpc-tab-root data-ajax-url="<?php echo esc_url( $ajax_url ); ?>" data-ajax-action="<?php echo esc_attr( $action ); ?>" data-nonce-field="<?php echo esc_attr( $nonce_key ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-page-url="<?php echo esc_url( $page_url ); ?>" data-panel-id="<?php echo esc_attr( $panel_id ); ?>" data-active-tab="<?php echo esc_attr( $active ); ?>">
            <nav class="hpc-host-tabs" role="tablist" aria-label="<?php echo esc_attr( $label ); ?>">
                <?php foreach ( $tabs as $id => $tab ) :
                    $id       = sanitize_key( (string) $id );
                    $tab_text = $this->tab_label( $tab );
                    $url      = $this->tab_url( $page_url, $id );
                    $is_on    = $id === $active;
                    ?>
                    <a class="hpc-host-tab<?php echo $is_on ? " active" : ""; ?>" href="<?php echo esc_url( $url ); ?>" data-hpc-host-tab="<?php echo esc_attr( $id ); ?>" role="tab" aria-selected="<?php echo $is_on ? "true" : "false"; ?>"><?php echo esc_html( $tab_text ); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="hpc-host-tab-status" aria-live="polite"><span class="spinner"></span><span data-hpc-tab-message><?php echo "" !== $active ? "Loaded " . esc_html( $this->tab_label( $tabs[ $active ] ?? $active ) ) . "." : ""; ?></span></div>
            <section id="<?php echo esc_attr( $panel_id ); ?>" class="hpc-host-tab-panel" data-hpc-tab-panel data-active-tab="<?php echo esc_attr( $active ); ?>" aria-live="polite"><?php echo $panel_html; ?></section>
        </div>
        <script>
        (function(){
            var root = document.getElementById("<?php echo esc_js( $root_id ); ?>");
            if (!root || root.dataset.hpcTabsReady === "1") return;
            root.dataset.hpcTabsReady = "1";
            var panel = document.getElementById(root.dataset.panelId || "");
            var status = root.querySelector(".hpc-host-tab-status");
            var spinner = status ? status.querySelector(".spinner") : null;
            var message = status ? status.querySelector("[data-hpc-tab-message]") : null;
            function tabs(){ return Array.prototype.slice.call(root.querySelectorAll("[data-hpc-host-tab]")); }
            function setMessage(text){ if (message) message.textContent = text || ""; }
            function setLoading(on){ if (!panel) return; panel.classList.toggle("is-loading", !!on); panel.setAttribute("aria-busy", on ? "true" : "false"); if (spinner) spinner.classList.toggle("is-active", !!on); }
            function eventName(name){ return "hexa-core-host-tab-" + name; }
            function dispatch(name, detail){ detail = detail || {}; detail.root = root; detail.panel = panel; root.dispatchEvent(new CustomEvent(eventName(name), { bubbles: true, detail: detail })); document.dispatchEvent(new CustomEvent(eventName(name), { detail: detail })); }
            function setActive(tab){ tabs().forEach(function(item){ var on = item.getAttribute("data-hpc-host-tab") === tab; item.classList.toggle("active", on); item.setAttribute("aria-selected", on ? "true" : "false"); }); root.dataset.activeTab = tab; if (panel) panel.setAttribute("data-active-tab", tab); }
            function tabUrl(tab){ var base = root.dataset.pageUrl || window.location.href; try { var u = new URL(base, window.location.origin); u.searchParams.set("tab", tab); return u.toString(); } catch(e) { return base + (base.indexOf("?") === -1 ? "?" : "&") + "tab=" + encodeURIComponent(tab); } }
            function runScripts(container){
                if (!container || !container.querySelectorAll) return;
                Array.prototype.slice.call(container.querySelectorAll("script")).forEach(function(source){
                    var script = document.createElement("script");
                    Array.prototype.slice.call(source.attributes || []).forEach(function(attr){ script.setAttribute(attr.name, attr.value); });
                    script.text = source.textContent || "";
                    if (source.parentNode) source.parentNode.replaceChild(script, source);
                    else document.head.appendChild(script).parentNode.removeChild(script);
                });
            }
            function load(tab, href, push){
                if (!tab || !panel || panel.dataset.loading === "1") return;
                panel.dataset.loading = "1";
                setLoading(true);
                setMessage("Loading...");
                dispatch("before-load", { tab: tab });
                var body = new URLSearchParams();
                body.set("action", root.dataset.ajaxAction || "");
                body.set(root.dataset.nonceField || "nonce", root.dataset.nonce || "");
                body.set("tab", tab);
                fetch(root.dataset.ajaxUrl || ajaxurl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString() })
                    .then(function(response){ return response.json(); })
                    .then(function(payload){
                        if (!payload || !payload.success || !payload.data) throw new Error("Tab response failed.");
                        panel.innerHTML = payload.data.html || "";
                        runScripts(panel);
                        setActive(payload.data.tab || tab);
                        setMessage("Loaded " + (payload.data.label || payload.data.tab || tab) + ".");
                        if (push !== false && window.history && history.pushState) history.pushState({ hpcHostTab: payload.data.tab || tab }, "", href || tabUrl(payload.data.tab || tab));
                        dispatch("loaded", { tab: payload.data.tab || tab, label: payload.data.label || "", html: payload.data.html || "" });
                    })
                    .catch(function(){ setMessage("Error loading tab."); dispatch("error", { tab: tab }); })
                    .finally(function(){ delete panel.dataset.loading; setLoading(false); });
            }
            tabs().forEach(function(tab){ tab.addEventListener("click", function(event){ var id = tab.getAttribute("data-hpc-host-tab"); if (!id) return; event.preventDefault(); if (id === root.dataset.activeTab) return; load(id, tab.getAttribute("href"), true); }); });
            window.addEventListener("popstate", function(){ var params = new URLSearchParams(window.location.search); var tab = params.get("tab") || root.dataset.activeTab || ""; load(tab, tabUrl(tab), false); });
        })();
        </script>
        <?php
    }

    private function tab_label( mixed $tab ): string {
        if ( $tab instanceof TabDefinition ) {
            return $tab->label . ( $tab->deprecated ? " (Deprecated)" : "" );
        }

        if ( is_array( $tab ) && isset( $tab["label"] ) ) {
            return (string) $tab["label"];
        }

        return (string) $tab;
    }

    private function tab_url( string $page_url, string $tab ): string {
        if ( function_exists( "add_query_arg" ) ) {
            return add_query_arg( "tab", $tab, $page_url );
        }

        return $page_url . ( str_contains( $page_url, "?" ) ? "&" : "?" ) . "tab=" . rawurlencode( $tab );
    }
}
