<?php

namespace Hexa\PluginCore\EntitySources;

use Hexa\PluginCore\SmartSearch\SmartSearchRenderer;
use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class PrimaryEntityRenderer {
    public function render( PrimaryEntityManager $manager, array $args = [] ): string {
        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();
        $settings = $manager->settings();
        $entity = $manager->resolve();
        $sources = $manager->sources();
        $nonce = wp_create_nonce( (string) $manager->config( 'nonce_action', 'hexa_primary_entity' ) );

        ob_start();
        ?>
        <?php echo $assets; ?>
        <?php echo $this->assets(); ?>
        <div class="hpc-ui hpc-primary-entity" data-hpc-primary-entity data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( (string) $manager->config( 'ajax_action', 'hexa_save_primary_entity' ) ); ?>" data-nonce-field="<?php echo esc_attr( (string) $manager->config( 'nonce_field', 'nonce' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <section class="hpc-card">
                <h3><?php echo esc_html( (string) ( $args['title'] ?? 'Website & Primary Entity' ) ); ?></h3>
                <p>Website classification is always available. A primary entity is optional and is only used by plugins that need a canonical person, organization, publication, or verified profile.</p>
                <div class="hpc-primary-entity-fields">
                    <label class="hpc-field"><span>Website type</span><select class="hpc-primary-site-type"><?php foreach ( $manager->site_types() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $manager->site_type(), $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                    <div class="hpc-field"><span>Primary entity</span><?php echo CoreUi::toggle( 'primary_entity_enabled', ! empty( $settings['enabled'] ), 'Use a primary entity on this site', [ 'class' => 'hpc-primary-enabled' ] ); ?></div>
                    <label class="hpc-field"><span>Semantic entity type</span><select class="hpc-primary-type"><option value="auto" <?php selected( $settings['entity_type'], 'auto' ); ?>>Detect automatically</option><option value="person" <?php selected( $settings['entity_type'], 'person' ); ?>>Person</option><option value="organization" <?php selected( $settings['entity_type'], 'organization' ); ?>>Organization</option><option value="publication" <?php selected( $settings['entity_type'], 'publication' ); ?>>Publication</option></select></label>
                    <label class="hpc-field"><span>Entity source</span><select class="hpc-primary-source"><option value="">Select a source</option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( $source['id'] ); ?>" <?php selected( $settings['source'], $source['id'] ); ?>><?php echo esc_html( $source['label'] ); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="hpc-primary-searches">
                    <?php foreach ( $sources as $source ) : ?>
                        <?php $selected = $settings['source'] === $source['id'] ? $entity : null; ?>
                        <div class="hpc-primary-source-search" data-source-id="<?php echo esc_attr( $source['id'] ); ?>" <?php echo $settings['source'] === $source['id'] ? '' : 'hidden'; ?>>
                            <?php
                            ob_start();
                            ( new SmartSearchRenderer() )->render(
                                [
                                    'id' => 'hpc-primary-search-' . $source['id'], 'label' => 'Select ' . $source['label'],
                                    'placeholder' => 'Search ' . strtolower( $source['label'] ) . '...',
                                    'source' => 'user' === $source['kind'] ? 'users' : 'posts', 'post_type' => $source['post_type'] ?: 'any',
                                    'value' => $selected['id'] ?? '', 'selected_name' => $selected['name'] ?? '', 'selected_subtitle' => $selected['subtitle'] ?? '',
                                    'class' => 'hpc-primary-object-search',
                                ]
                            );
                            echo ob_get_clean();
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="hpc-actions hpc-actions-bottom"><button type="button" class="hpc-button hpc-primary-save">Save Website Profile</button><span class="hpc-primary-status" aria-live="polite"></span></div>
            </section>
            <?php echo $this->entity_preview( $entity, $manager, $args ); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed>|null $entity */
    private function entity_preview( ?array $entity, PrimaryEntityManager $manager, array $args ): string {
        if ( ! $entity ) {
            return '<section class="hpc-card hpc-primary-empty"><h3>No primary entity assigned</h3><p>This is a valid configuration. HWS and unrelated features continue to operate without an author profile.</p></section>';
        }
        $groups = ( new EntityFieldInspector() )->inspect( $entity );
        ob_start();
        ?>
        <section class="hpc-card hpc-primary-preview">
            <div class="hpc-primary-heading"><?php if ( $entity['image_url'] ) : ?><img src="<?php echo esc_url( $entity['image_url'] ); ?>" alt=""><?php endif; ?><div><h3><?php echo esc_html( $entity['name'] ); ?></h3><div class="hpc-actions"><?php echo CoreUi::pill( ucfirst( $entity['entity_type'] ), 'success' ); ?><?php echo CoreUi::pill( ucfirst( $entity['kind'] ), 'dark' ); ?><?php echo CoreUi::pill( 'ID ' . $entity['id'], 'dark' ); ?></div></div></div>
            <div class="hpc-actions"><?php if ( $entity['edit_url'] ) : ?><?php echo CoreUi::external_link( $entity['edit_url'], 'Edit source' ); ?><?php endif; ?><?php if ( $entity['view_url'] ) : ?><?php echo CoreUi::external_link( $entity['view_url'], 'View publicly' ); ?><?php endif; ?></div>
            <?php if ( 'post' === $entity['kind'] && ! empty( $entity['attached_user_id'] ) ) : ?><p class="hpc-primary-bound-user"><strong>Bound WordPress author:</strong> <?php echo esc_html( $entity['attached_user_name'] ?: 'User ' . $entity['attached_user_id'] ); ?> <span class="hpc-code">ID <?php echo (int) $entity['attached_user_id']; ?></span><?php if ( ! empty( $entity['attached_user_edit_url'] ) ) : ?> <?php echo CoreUi::external_link( $entity['attached_user_edit_url'], 'Edit author' ); ?><?php endif; ?></p><?php endif; ?>
            <?php if ( ! empty( $entity['settings']['migrated_from'] ) ) : ?><p class="hpc-small">Migrated from: <?php echo esc_html( $entity['settings']['migrated_from'] ); ?></p><?php endif; ?>
            <div class="hpc-primary-field-groups">
                <?php foreach ( $groups as $group ) : ?>
                    <?php echo CoreUi::collapsible( [ 'title' => $group['label'], 'meta_html' => CoreUi::pill( count( $group['fields'] ) . ' fields', 'dark' ), 'body_html' => $this->field_table( $group['fields'] ), 'open' => false, 'persist_key' => 'primary-entity-' . $entity['id'] . '-' . sanitize_key( $group['key'] ), 'query_state' => false ] ); ?>
                <?php endforeach; ?>
            </div>
            <?php if ( ! empty( $args['consumers'] ) ) : ?><?php echo $this->consumer_status( (array) $args['consumers'], $entity ); ?><?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<int,array<string,mixed>> $fields */
    private function field_table( array $fields ): string {
        $html = '<div class="hpc-primary-field-table"><div class="head">Field</div><div class="head">Type</div><div class="head">Current value</div>';
        foreach ( $fields as $field ) {
            $html .= '<div><strong>' . esc_html( $field['label'] ) . '</strong><small>' . esc_html( $field['name'] ) . '</small></div><div>' . esc_html( $field['type'] ) . '</div><div class="' . ( $field['set'] ? '' : 'empty' ) . '">' . esc_html( $field['value'] ) . '</div>';
        }
        return $html . '</div>';
    }

    /** @param array<int,array<string,mixed>> $consumers @param array<string,mixed> $entity */
    private function consumer_status( array $consumers, array $entity ): string {
        $html = '<h4>Plugin consumers</h4><div class="hpc-toggle-list">';
        foreach ( $consumers as $consumer ) {
            $active = isset( $consumer['active'] ) && is_callable( $consumer['active'] ) ? (bool) call_user_func( $consumer['active'], $entity ) : (bool) ( $consumer['active'] ?? false );
            $html .= '<div class="hpc-toggle-row">' . CoreUi::pill( $active ? 'Connected' : 'Not active', $active ? 'success' : 'warning' ) . '<div><strong>' . esc_html( (string) ( $consumer['label'] ?? 'Plugin' ) ) . '</strong><p>' . esc_html( (string) ( $consumer['description'] ?? '' ) ) . '</p></div></div>';
        }
        return $html . '</div>';
    }

    private function assets(): string {
        static $done = false;
        if ( $done ) return '';
        $done = true;
        return <<<'HTML'
<style>.hpc-primary-entity{display:grid;gap:14px}.hpc-primary-entity-fields{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:16px}.hpc-primary-searches{margin-top:4px}.hpc-primary-heading{align-items:center;display:flex;gap:14px;margin-bottom:14px}.hpc-primary-heading img{border:1px solid var(--hpc-line);border-radius:8px;height:84px;object-fit:cover;width:84px}.hpc-primary-heading h3{font-size:20px}.hpc-primary-field-groups{margin-top:16px}.hpc-primary-field-table{display:grid;grid-template-columns:minmax(160px,.8fr) 120px minmax(240px,1.5fr);overflow-wrap:anywhere}.hpc-primary-field-table>div{border-bottom:1px solid #e7ebf1;padding:9px}.hpc-primary-field-table .head{background:#f5f7fa;font-size:11px;font-weight:800;text-transform:uppercase}.hpc-primary-field-table small{color:var(--hpc-muted);display:block;margin-top:3px}.hpc-primary-field-table .empty{color:var(--hpc-muted);font-style:italic}.hpc-primary-entity.is-saving{opacity:.7;pointer-events:none}.hpc-primary-status{color:var(--hpc-muted);font-size:13px}@media(max-width:760px){.hpc-primary-entity-fields,.hpc-primary-field-table{grid-template-columns:1fr}.hpc-primary-field-table .head{display:none}.hpc-primary-field-table>div{border-bottom:0}.hpc-primary-field-table>div:nth-child(3n){border-bottom:1px solid #e7ebf1}}</style>
<script>(function(){if(window.hexaPrimaryEntityReady)return;window.hexaPrimaryEntityReady=true;document.addEventListener('change',function(event){var source=event.target.closest('.hpc-primary-source');if(!source)return;var root=source.closest('[data-hpc-primary-entity]');root.querySelectorAll('.hpc-primary-source-search').forEach(function(item){item.hidden=item.dataset.sourceId!==source.value;});});document.addEventListener('click',function(event){var button=event.target.closest('.hpc-primary-save');if(!button)return;var root=button.closest('[data-hpc-primary-entity]');var source=root.querySelector('.hpc-primary-source').value||'';var search=root.querySelector('.hpc-primary-source-search[data-source-id="'+CSS.escape(source)+'"]');var objectId=search?search.querySelector('.hpc-smart-search-value').value:'';var status=root.querySelector('.hpc-primary-status');var body=new URLSearchParams();body.set('action',root.dataset.action||'');body.set(root.dataset.nonceField||'nonce',root.dataset.nonce||'');body.set('site_type',root.querySelector('.hpc-primary-site-type').value||'');body.set('enabled',root.querySelector('.hpc-primary-enabled input').checked?'1':'0');body.set('source',source);body.set('object_id',objectId);body.set('entity_type',root.querySelector('.hpc-primary-type').value||'auto');root.classList.add('is-saving');if(status)status.textContent='Saving...';fetch(root.dataset.ajaxUrl||window.ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(response){return response.json()}).then(function(payload){if(!payload||!payload.success)throw new Error(payload&&payload.data&&payload.data.message?payload.data.message:'Save failed');if(status)status.textContent=payload.data.message||'Saved.';}).catch(function(error){if(status)status.textContent=error.message||'Unable to save.';}).finally(function(){root.classList.remove('is-saving')});});})();</script>
HTML;
    }
}
