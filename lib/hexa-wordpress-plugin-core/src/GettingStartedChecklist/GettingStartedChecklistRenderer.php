<?php

namespace Hexa\PluginCore\GettingStartedChecklist;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;

final class GettingStartedChecklistRenderer {
    private GettingStartedChecklistConfig $config;

    public function __construct( GettingStartedChecklistConfig|array $config ) {
        $this->config = is_array( $config ) ? new GettingStartedChecklistConfig( $config ) : $config;
    }

    public function render(): void {
        CoreUi::render_assets();
        DynamicButton::render_assets();

        $root_id             = $this->config->root_id();
        $default_template_id = $this->config->default_template_id();
        $templates           = $this->config->templates();
        $steps               = $this->config->template_steps( $default_template_id );
        $nonce               = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->config->nonce_action() ) : '';
        ?>
        <div id="<?php echo esc_attr( $root_id ); ?>" class="hpc-ui hpc-gsc" data-hpc-getting-started-checklist data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-run-action="<?php echo esc_attr( $this->config->run_action() ); ?>" data-nonce-field="<?php echo esc_attr( $this->config->nonce_field() ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-default-template-id="<?php echo esc_attr( $default_template_id ); ?>" data-current-template-id="<?php echo esc_attr( $default_template_id ); ?>">
            <?php echo $this->assets( $root_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php
            ob_start();
            ?>
                <?php if ( '' !== $this->config->description() ) : ?>
                    <p class="hpc-gsc-description"><?php echo esc_html( $this->config->description() ); ?></p>
                <?php endif; ?>

                <?php echo $this->template_picker_html( $templates, $default_template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="hpc-gsc-actions">
                    <?php echo DynamicButton::render( [ 'label' => 'Run Checklist', 'working_label' => 'Running...', 'success_label' => 'Checklist Finished', 'error_label' => 'Checklist Failed', 'class' => 'hpc-button', 'attrs' => [ 'data-gsc-run-all' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <button type="button" class="hpc-button secondary" data-gsc-reset>Reset UI</button>
                </div>

                <?php if ( [] === $steps ) : ?>
                    <div class="hpc-callout"><?php echo esc_html( $this->config->empty_message() ); ?></div>
                <?php else : ?>
                    <div class="hpc-gsc-list" data-gsc-list>
                        <?php foreach ( $steps as $step ) : ?>
                            <?php echo $this->step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php echo $this->template_store_html( $templates ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <details class="hpc-gsc-log">
                    <summary class="hpc-gsc-log-head">
                        <div>
                            <h3>Technical Activity Log</h3>
                            <span>Reports each AJAX request, callback result, subtask transition, and failure message.</span>
                        </div>
                        <span class="hpc-gsc-log-controls">
                            <span class="hpc-gsc-log-chevron" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                            <button type="button" class="hpc-button secondary" data-gsc-clear-log>Clear</button>
                        </span>
                    </summary>
                    <div class="hpc-gsc-log-body" data-gsc-log-body aria-live="polite">
                        <?php echo $this->log_row( [ 'time' => 'Ready', 'level' => 'info', 'message' => 'Checklist runner is ready.', 'context' => [] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </details>
            <?php
            $body = (string) ob_get_clean();

            echo CoreUi::collapsible(
                [
                    'title'       => $this->config->title(),
                    'open'        => true,
                    'persist_key' => $root_id . '-panel',
                    'meta_html'   => CoreUi::pill( count( $steps ) . ' steps', 'dark' ) . ( count( $templates ) > 1 ? CoreUi::pill( count( $templates ) . ' templates', 'blue' ) : '' ),
                    'body_html'   => $body,
                ]
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php
    }

    /**
     * @param array<string,GettingStartedChecklistTemplate> $templates
     */
    private function template_picker_html( array $templates, string $default_template_id ): string {
        if ( ! $this->config->show_template_picker() || [] === $templates ) {
            return '';
        }

        $current = $templates[ $default_template_id ] ?? reset( $templates );
        ob_start();
        ?>
        <div class="hpc-gsc-template-picker" data-gsc-template-picker>
            <label for="<?php echo esc_attr( $this->config->root_id() . '-template' ); ?>">
                <span><?php echo esc_html( $this->config->template_label() ); ?></span>
                <select id="<?php echo esc_attr( $this->config->root_id() . '-template' ); ?>" data-gsc-template-select>
                    <?php foreach ( $templates as $template ) : ?>
                        <option value="<?php echo esc_attr( $template->id ); ?>" <?php selected( $template->id, $default_template_id ); ?> data-step-count="<?php echo esc_attr( (string) count( $template->steps ) ); ?>" data-description="<?php echo esc_attr( $template->description ); ?>"><?php echo esc_html( $template->label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="button" class="hpc-button secondary" data-gsc-load-template><?php echo esc_html( $this->config->template_load_label() ); ?></button>
            <span class="hpc-gsc-template-status" data-gsc-template-status><?php echo esc_html( $current instanceof GettingStartedChecklistTemplate ? $current->label . ' loaded' : 'Template ready' ); ?></span>
            <?php if ( $current instanceof GettingStartedChecklistTemplate && '' !== $current->description ) : ?>
                <small data-gsc-template-description><?php echo esc_html( $current->description ); ?></small>
            <?php else : ?>
                <small data-gsc-template-description></small>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,GettingStartedChecklistTemplate> $templates
     */
    private function template_store_html( array $templates ): string {
        if ( [] === $templates ) {
            return '';
        }

        ob_start();
        ?>
        <div hidden data-gsc-template-store>
            <?php foreach ( $templates as $template ) : ?>
                <template data-gsc-template-source="<?php echo esc_attr( $template->id ); ?>" data-template-label="<?php echo esc_attr( $template->label ); ?>" data-template-description="<?php echo esc_attr( $template->description ); ?>" data-step-count="<?php echo esc_attr( (string) count( $template->steps ) ); ?>">
                    <?php foreach ( $template->steps as $step ) : ?>
                        <?php echo $this->step_html( $step ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </template>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function step_html( GettingStartedChecklistStep $step ): string {
        $subtasks = $step->subtasks;
        $has_subtasks = [] !== $subtasks;
        $show_type_badges = $this->config->show_type_badges();

        ob_start();
        ?>
        <?php if ( $has_subtasks ) : ?>
            <details class="hpc-gsc-step hpc-gsc-step-parent" data-gsc-step-card data-step-id="<?php echo esc_attr( $step->id ); ?>" open>
                <summary class="hpc-gsc-row hpc-gsc-step-row" data-gsc-item data-gsc-step-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="" data-request-type="<?php echo esc_attr( $step->type ); ?>" data-has-action="<?php echo $step->has_callback() ? '1' : '0'; ?>" data-has-subtasks="1" data-has-required-inputs="<?php echo [] !== $step->required_inputs ? '1' : '0'; ?>" data-status="pending">
                    <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="hpc-gsc-main">
                        <div class="hpc-gsc-title-line">
                            <strong><?php echo esc_html( $step->label ); ?></strong>
                            <?php if ( $show_type_badges ) : ?>
                                <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $step->type ) ); ?></span>
                            <?php endif; ?>
                            <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                        </div>
                        <?php if ( '' !== $step->description ) : ?>
                            <p><?php echo esc_html( $step->description ); ?></p>
                        <?php endif; ?>
                        <?php echo $this->required_inputs_html( $step->required_inputs, $step->id, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="hpc-gsc-report" data-gsc-report hidden></div>
                    </div>
                    <div class="hpc-gsc-row-action">
                        <span class="hpc-gsc-section-toggle" aria-hidden="true"><svg viewBox="0 0 512 512" focusable="false"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg></span>
                        <?php echo DynamicButton::render( [ 'label' => $step->action_label . ' Step', 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-step' => true, 'data-step-id' => $step->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </summary>
                <div class="hpc-gsc-subtasks" data-gsc-subtasks="<?php echo esc_attr( $step->id ); ?>">
                    <?php foreach ( $subtasks as $subtask ) : ?>
                        <div class="hpc-gsc-row hpc-gsc-subtask-row" data-gsc-item data-gsc-subtask-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="<?php echo esc_attr( $subtask->id ); ?>" data-request-type="<?php echo esc_attr( $subtask->type ); ?>" data-has-action="<?php echo $subtask->has_callback() ? '1' : '0'; ?>" data-has-required-inputs="<?php echo [] !== $subtask->required_inputs ? '1' : '0'; ?>" data-status="pending">
                            <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <div class="hpc-gsc-main">
                                <div class="hpc-gsc-title-line">
                                    <strong><?php echo esc_html( $subtask->label ); ?></strong>
                                    <?php if ( $show_type_badges ) : ?>
                                        <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $subtask->type ) ); ?></span>
                                    <?php endif; ?>
                                    <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                                </div>
                                <?php if ( '' !== $subtask->description ) : ?>
                                    <p><?php echo esc_html( $subtask->description ); ?></p>
                                <?php endif; ?>
                                <?php echo $this->required_inputs_html( $subtask->required_inputs, $step->id, $subtask->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <div class="hpc-gsc-report" data-gsc-report hidden></div>
                            </div>
                            <div class="hpc-gsc-row-action">
                                <?php echo DynamicButton::render( [ 'label' => $subtask->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-item' => true, 'data-step-id' => $step->id, 'data-subtask-id' => $subtask->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php else : ?>
            <div class="hpc-gsc-step hpc-gsc-step-single" data-gsc-step-card data-step-id="<?php echo esc_attr( $step->id ); ?>">
                <div class="hpc-gsc-row hpc-gsc-step-row" data-gsc-item data-gsc-step-row data-step-id="<?php echo esc_attr( $step->id ); ?>" data-subtask-id="" data-request-type="<?php echo esc_attr( $step->type ); ?>" data-has-action="<?php echo $step->has_callback() ? '1' : '0'; ?>" data-has-subtasks="0" data-has-required-inputs="<?php echo [] !== $step->required_inputs ? '1' : '0'; ?>" data-status="pending">
                    <?php echo $this->status_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="hpc-gsc-main">
                        <div class="hpc-gsc-title-line">
                            <strong><?php echo esc_html( $step->label ); ?></strong>
                            <?php if ( $show_type_badges ) : ?>
                                <span class="hpc-gsc-type"><?php echo esc_html( $this->type_label( $step->type ) ); ?></span>
                            <?php endif; ?>
                            <span class="hpc-gsc-state" data-gsc-state>Pending</span>
                        </div>
                        <?php if ( '' !== $step->description ) : ?>
                            <p><?php echo esc_html( $step->description ); ?></p>
                        <?php endif; ?>
                        <?php echo $this->required_inputs_html( $step->required_inputs, $step->id, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <div class="hpc-gsc-report" data-gsc-report hidden></div>
                    </div>
                    <div class="hpc-gsc-row-action">
                        <?php echo DynamicButton::render( [ 'label' => $step->action_label, 'working_label' => 'Running...', 'success_label' => 'Done', 'error_label' => 'Failed', 'class' => 'hpc-button secondary', 'attrs' => [ 'data-gsc-run-step' => true, 'data-step-id' => $step->id ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param array<int,array<string,mixed>> $inputs
     */
    private function required_inputs_html( array $inputs, string $step_id, string $subtask_id ): string {
        if ( [] === $inputs ) {
            return '';
        }

        ob_start();
        ?>
        <div class="hpc-gsc-inputs" data-gsc-inputs>
            <?php foreach ( $inputs as $input ) : ?>
                <?php
                $id          = (string) ( $input['id'] ?? '' );
                $label       = (string) ( $input['label'] ?? $id );
                $type           = (string) ( $input['type'] ?? 'text' );
                $field_type     = 'confirmation' === $type ? 'text' : $type;
                $required       = (bool) ( $input['required'] ?? true );
                $description    = (string) ( $input['description'] ?? '' );
                $confirm_text   = (string) ( $input['confirm_text'] ?? '' );
                $case_sensitive = (bool) ( $input['case_sensitive'] ?? true );
                $field_id       = 'hpc-gsc-input-' . $step_id . ( '' !== $subtask_id ? '-' . $subtask_id : '' ) . '-' . $id;

                if ( '' === $id ) {
                    continue;
                }
                ?>
                <label class="hpc-gsc-input-field" for="<?php echo esc_attr( $field_id ); ?>">
                    <span>
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $required ) : ?>
                            <em>Required</em>
                        <?php endif; ?>
                    </span>
                    <input
                        id="<?php echo esc_attr( $field_id ); ?>"
                        type="<?php echo esc_attr( $field_type ); ?>"
                        value="<?php echo esc_attr( (string) ( $input['value'] ?? '' ) ); ?>"
                        placeholder="<?php echo esc_attr( (string) ( $input['placeholder'] ?? '' ) ); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                        <?php echo '' !== (string) ( $input['pattern'] ?? '' ) ? 'pattern="' . esc_attr( (string) $input['pattern'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['min'] ?? '' ) ? 'min="' . esc_attr( (string) $input['min'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['max'] ?? '' ) ? 'max="' . esc_attr( (string) $input['max'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['step'] ?? '' ) ? 'step="' . esc_attr( (string) $input['step'] ) . '"' : ''; ?>
                        <?php echo '' !== (string) ( $input['autocomplete'] ?? '' ) ? 'autocomplete="' . esc_attr( (string) $input['autocomplete'] ) . '"' : ''; ?>
                        data-gsc-input
                        data-input-id="<?php echo esc_attr( $id ); ?>"
                        data-input-type="<?php echo esc_attr( $type ); ?>"
                        data-input-label="<?php echo esc_attr( $label ); ?>"
                        data-confirm-text="<?php echo esc_attr( $confirm_text ); ?>"
                        data-case-sensitive="<?php echo $case_sensitive ? '1' : '0'; ?>"
                    >
                    <?php if ( '' !== $description ) : ?>
                        <small><?php echo esc_html( $description ); ?></small>
                    <?php endif; ?>
                    <small class="hpc-gsc-input-error" data-gsc-input-error></small>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function assets( string $root_id ): string {
        ob_start();
        ?>
        <style>
            #<?php echo esc_attr( $root_id ); ?>{max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-description{color:#3f4d63;font-size:13px;line-height:1.55;margin:0 0 14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker{align-items:flex-end;background:#f8fbff;border:1px solid #dce7f4;border-radius:8px;display:flex;flex-wrap:wrap;gap:10px;margin:0 0 14px;padding:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker label{display:grid;gap:5px;min-width:min(280px,100%)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker label span{color:#243044;font-size:12px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker select{background:#fff;border:1px solid #cbd6e2;border-radius:6px;box-shadow:none;color:#111827;font-size:13px;min-height:34px;padding:5px 30px 5px 10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-status{background:#eaf8ef;border:1px solid #ccefd7;border-radius:999px;color:var(--hpc-green);font-size:11px;font-weight:900;line-height:1;padding:7px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-template-picker small{color:var(--hpc-muted);display:block;flex-basis:100%;font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-list{background:#fff;border:1px solid var(--hpc-line);border-radius:8px;display:block;margin:0 0 16px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step{background:#fff;border:0;border-top:1px solid var(--hpc-line);border-radius:0;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{align-items:flex-start;display:grid;gap:12px;grid-template-columns:34px minmax(0,1fr) auto;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step-row{background:#fff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step-parent[open]>.hpc-gsc-step-row{background:#fbfcfe}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtasks{border-top:1px solid #edf1f6;display:grid;gap:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{border-top:1px solid #edf1f6;margin-left:34px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{align-items:center;display:flex;gap:10px;justify-content:flex-end}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions{position:relative}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:hover::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:focus-within::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:hover::after,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:focus-within::after{background:#111827;border:1px solid #263241;border-radius:6px;box-shadow:0 10px 24px rgba(15,23,42,.24);color:#f8fafc;content:attr(data-disabled-reason);font-size:12px;font-weight:700;line-height:1.35;max-width:min(360px,calc(100vw - 40px));padding:8px 10px;pointer-events:none;position:absolute;right:0;text-align:left;top:calc(100% + 8px);white-space:normal;width:max-content;z-index:50}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:hover::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action[data-disabled-reason]:focus-within::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:hover::before,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-actions[data-disabled-reason]:focus-within::before{border:7px solid transparent;border-bottom-color:#111827;content:"";pointer-events:none;position:absolute;right:20px;top:calc(100% - 5px);z-index:51}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:var(--hpc-blue);display:inline-flex;height:28px;justify-content:center;transition:background .18s,border-color .18s,color .18s;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-section-toggle svg{display:block;fill:currentColor;height:12px;transform:rotate(180deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step:not([open]) .hpc-gsc-section-toggle svg{transform:rotate(0deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:hover .hpc-gsc-section-toggle{background:#e4ebff;border-color:#c7d4ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-step summary:focus-visible{box-shadow:inset 0 0 0 2px var(--hpc-blue);outline:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main{min-width:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-title-line strong{font-size:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-main p{color:var(--hpc-muted);font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report{display:grid;gap:8px;margin-top:10px;max-width:100%;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report[hidden]{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-card{background:#f8fafc;border:1px solid #dce5ef;border-radius:7px;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head{border-bottom:1px solid #dce5ef;padding:9px 11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head strong{display:block;font-size:12px;margin:0 0 3px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-head span{color:var(--hpc-muted);display:block;font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-explain{background:#fff;border-bottom:1px solid #dce5ef;display:grid;gap:8px;padding:11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-doc{color:#334155;font-size:12px;line-height:1.45;margin:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list{display:grid;gap:7px;margin:0;padding:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list li{background:#f8fafc;border:1px solid #e4ebf3;border-radius:6px;color:#243044;display:grid;font-size:12px;gap:3px;line-height:1.4;list-style:none;margin:0;padding:8px 9px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-summary-list strong{color:#111827;font-size:11px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));padding:11px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-card{background:#fff;border:1px solid #dce5ef;border-radius:8px;color:#243044;display:grid;gap:8px;padding:10px;text-decoration:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-card:hover{border-color:var(--hpc-blue);box-shadow:0 6px 16px rgba(30,64,175,.10)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-frame{align-items:center;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:7px;display:flex;height:132px;justify-content:center;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-frame img{display:block;height:auto;image-rendering:auto;max-height:112px;max-width:112px;object-fit:contain;width:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-label{color:#1f2937;font-size:12px;font-weight:900;line-height:1.25}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-meta,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-preview-url{color:var(--hpc-muted);font-size:11px;line-height:1.35;overflow-wrap:anywhere}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-scroll{max-width:100%;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table{border-collapse:collapse;font-size:11px;min-width:100%;table-layout:auto;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table th,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table td{border-bottom:1px solid #e7eef6;padding:7px 9px;text-align:left;vertical-align:top}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table th{background:#eef4fb;color:#334155;font-size:10px;font-weight:900;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table td{color:#243044;overflow-wrap:anywhere;word-break:break-word}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table tr:last-child td{border-bottom:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-report-table a{color:var(--hpc-blue);font-weight:800;text-decoration:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-inputs{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:10px;max-width:760px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field{display:grid;gap:5px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field span{align-items:center;color:#243044;display:flex;font-size:12px;font-weight:800;gap:7px;line-height:1.2}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field em{background:#fff4dd;border:1px solid #ffdca8;border-radius:999px;color:#8a5200;font-size:10px;font-style:normal;font-weight:900;line-height:1;padding:4px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input{background:#fff;border:1px solid #cbd6e2;border-radius:6px;box-shadow:none;color:#111827;font-size:13px;line-height:1.4;min-height:34px;padding:6px 10px;width:100%}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input:focus{border-color:var(--hpc-blue);box-shadow:0 0 0 1px var(--hpc-blue);outline:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field input[aria-invalid="true"]{border-color:var(--hpc-red);box-shadow:0 0 0 1px var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field small{color:var(--hpc-muted);font-size:11px;line-height:1.35}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-input-field .hpc-gsc-input-error{color:var(--hpc-red);font-weight:700;min-height:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-state{background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#475569;font-size:11px;font-weight:800;line-height:1;padding:5px 8px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-type{background:#fff;border:1px solid #d8e1ec;border-radius:5px;color:#536173;font-size:11px;font-weight:800;line-height:1;padding:5px 7px;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-state{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-state{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-state{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon{align-items:center;background:#eef2f7;border:1px solid #d7e0ea;border-radius:999px;color:#64748b;display:inline-flex;height:30px;justify-content:center;margin-top:1px;width:30px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-status-icon svg{display:block;fill:currentColor;height:14px;width:14px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-x,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-spinner{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-icon-pending{background:currentColor;border-radius:999px;display:block;height:8px;width:8px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-status-icon{background:#eef2ff;border-color:#c7d4ff;color:var(--hpc-blue)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-status-icon{background:#eaf8ef;border-color:#ccefd7;color:var(--hpc-green)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-status-icon{background:#fff0f2;border-color:#ffd0d8;color:var(--hpc-red)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-spinner{animation:hpc-gsc-spin .72s linear infinite;border:3px solid currentColor;border-right-color:transparent;border-radius:999px;display:block;height:17px;width:17px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-check,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-x{display:block}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="running"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="success"] .hpc-gsc-icon-pending,#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row[data-status="failed"] .hpc-gsc-icon-pending{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log{background:#0f1720;border:1px solid #263241;border-radius:8px;color:#dbe7f3;overflow:hidden}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary{cursor:pointer;list-style:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log summary::-webkit-details-marker{display:none}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head{align-items:center;background:#111c2a;display:flex;gap:12px;justify-content:space-between;padding:14px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-head{border-bottom:1px solid #263241}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head h3{color:#f8fafc;font-size:15px;margin:0 0 4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-head span{color:#9fb1c6;font-size:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-controls{align-items:center;display:inline-flex;gap:10px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron{align-items:center;background:#1f2f44;border:1px solid #34465d;border-radius:999px;color:#cbd5e1;display:inline-flex;height:28px;justify-content:center;width:28px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-chevron svg{fill:currentColor;height:12px;transform:rotate(0deg);transition:transform .18s;width:12px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log[open] .hpc-gsc-log-chevron svg{transform:rotate(180deg)}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-body{max-height:360px;overflow:auto}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{border-top:1px solid #1f2f44;display:grid;gap:10px;grid-template-columns:minmax(70px,92px) minmax(58px,82px) minmax(0,1fr);padding:12px 16px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row:first-child{border-top:0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-time{color:#8ca1b8;font-size:12px;white-space:nowrap}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level{border-radius:5px;font-size:11px;font-weight:900;letter-spacing:.04em;padding:3px 7px;text-align:center;text-transform:uppercase}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.info{background:#16324f;color:#9bd0ff}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.success{background:#14391f;color:#9cf0b0}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.warning{background:#493813;color:#ffd37a}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-level.error{background:#4c1720;color:#ff9cac}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-message{font-size:13px;font-weight:650;margin-bottom:4px}
            #<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-context{color:#9fb1c6;font-size:12px;overflow-wrap:anywhere;white-space:pre-wrap;word-break:break-word}
            @keyframes hpc-gsc-spin{to{transform:rotate(360deg)}}
            @media(max-width:760px){#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row{grid-template-columns:34px minmax(0,1fr)}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-row-action{grid-column:2;justify-content:flex-start}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-subtask-row{margin-left:0}#<?php echo esc_attr( $root_id ); ?> .hpc-gsc-log-row{grid-template-columns:1fr}}
        </style>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $root_id ); ?>');
            if (!root || root.dataset.gscReady === '1') return;
            root.dataset.gscReady = '1';
            var logBody = null;
            function text(value){ return value === null || value === undefined ? '' : String(value); }
            function esc(value){ var div = document.createElement('div'); div.textContent = text(value); return div.innerHTML; }
            function css(value){ if (window.CSS && CSS.escape) return CSS.escape(text(value)); return text(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }
            function now(){ var date = new Date(); return date.toTimeString().slice(0,8); }
            function getLogBody(){
                if (!logBody || !root.contains(logBody)) {
                    logBody = root.querySelector('[data-gsc-log-body]');
                }
                return logBody;
            }
            function actionContainer(button){ return button ? (button.closest('.hpc-gsc-row-action') || button.closest('.hpc-gsc-actions')) : null; }
            function setDisabledReason(button, reason){
                if (!button) return;
                reason = text(reason).trim();
                var container = actionContainer(button);
                if (reason) {
                    button.setAttribute('title', reason);
                    button.setAttribute('aria-disabled', 'true');
                    button.dataset.disabledReason = reason;
                    if (container) {
                        container.dataset.disabledReason = reason;
                        container.setAttribute('title', reason);
                    }
                } else {
                    button.removeAttribute('title');
                    button.removeAttribute('aria-disabled');
                    delete button.dataset.disabledReason;
                    if (container) {
                        delete container.dataset.disabledReason;
                        container.removeAttribute('title');
                    }
                }
            }
            function dynamicStart(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.start(button, label); else if (button) button.disabled = true; }
            function dynamicSuccess(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.success(button, label || 'Done'); else if (button) button.disabled = false; }
            function dynamicError(button, label){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.error(button, label || 'Failed'); else if (button) button.disabled = false; }
            function dynamicReset(button){ setDisabledReason(button, ''); if (window.HexaWpCoreDynamicButton) window.HexaWpCoreDynamicButton.reset(button); else if (button) button.disabled = false; }
            function logRow(entry){
                entry = entry || {};
                var context = entry.context && Object.keys(entry.context).length ? JSON.stringify(entry.context, null, 2) : '';
                return '<div class="hpc-gsc-log-row"><div class="hpc-gsc-log-time">' + esc(entry.time || now()) + '</div><div><span class="hpc-gsc-log-level ' + esc((entry.level || 'info').toLowerCase()) + '">' + esc(entry.level || 'info') + '</span></div><div><div class="hpc-gsc-log-message">' + esc(entry.message || '') + '</div>' + (context ? '<div class="hpc-gsc-log-context">' + esc(context) + '</div>' : '') + '</div></div>';
            }
            function addLog(entry){
                var target = getLogBody();
                if (!target) return;
                target.insertAdjacentHTML('beforeend', logRow(entry));
                target.scrollTop = target.scrollHeight;
            }
            function addLogs(logs){ (logs || []).forEach(addLog); }
            function clearLog(){
                var target = getLogBody();
                if (!target) return;
                target.innerHTML = logRow({time:'Ready', level:'info', message:'Checklist runner is ready.', context:{}});
            }
            function reportTarget(row){ return row ? row.querySelector('[data-gsc-report]') : null; }
            function clearReport(row){ var target = reportTarget(row); if (!target) return; target.hidden = true; target.innerHTML = ''; }
            function normalizeReports(payload){
                payload = payload || {};
                var reports = [];
                if (Array.isArray(payload.reports)) reports = payload.reports;
                else if (payload.report) reports = Array.isArray(payload.report) ? payload.report : [payload.report];
                return reports.filter(function(report){ return report && typeof report === 'object'; });
            }
            function reportColumns(report){
                if (Array.isArray(report.columns) && report.columns.length) return report.columns.map(function(col){ return typeof col === 'string' ? {key:col,label:col} : col; }).filter(function(col){ return col && col.key; });
                if (report.type === 'deleted_files') return [{key:'file',label:'File'},{key:'location',label:'Location'},{key:'size',label:'Size'}];
                if (report.type === 'wp_config_changes') return [{key:'setting',label:'Setting'},{key:'before',label:'Before Action'},{key:'after',label:'Requested Value'},{key:'actual',label:'Verified After'},{key:'meaning',label:'What Changed'},{key:'file',label:'File'}];
                if (report.type === 'deleted_posts') return [{key:'title',label:'Title'},{key:'id',label:'ID'},{key:'permalink',label:'Permalink'},{key:'media',label:'Deleted Media'}];
                var first = Array.isArray(report.items) && report.items.length ? report.items[0] : {};
                return Object.keys(first).map(function(key){ return {key:key,label:key.replace(/_/g,' ')}; });
            }
            function valueHtml(value, key){
                if (Array.isArray(value)) return value.map(function(item){ return valueHtml(item, key); }).join('<br>');
                if (value && typeof value === 'object') return '<code>' + esc(JSON.stringify(value)) + '</code>';
                value = text(value);
                if ((/url|link|permalink/i.test(key || '') || /^https?:\/\//i.test(value)) && /^https?:\/\//i.test(value)) return '<a href="' + esc(value).replace(/"/g,'&quot;') + '" target="_blank" rel="noopener noreferrer">' + esc(value) + '</a>';
                return esc(value);
            }
            function reportExplainHtml(report){
                var meta = report && report.meta && typeof report.meta === 'object' ? report.meta : {};
                var documentation = text(meta.documentation || meta.explanation || '').trim();
                var items = Array.isArray(meta.summary_items) ? meta.summary_items : [];
                items = items.filter(function(item){ return item && (text(item.label || '').trim() || text(item.value || '').trim()); });
                if (!documentation && !items.length) return '';
                var html = '<div class="hpc-gsc-report-explain">';
                if (documentation) html += '<p class="hpc-gsc-report-doc">' + esc(documentation) + '</p>';
                if (items.length) {
                    html += '<ul class="hpc-gsc-report-summary-list">' + items.map(function(item){
                        var label = text(item.label || '').trim();
                        var value = text(item.value || '').trim();
                        return '<li>' + (label ? '<strong>' + esc(label) + '</strong>' : '') + '<span>' + esc(value) + '</span></li>';
                    }).join('') + '</ul>';
                }
                html += '</div>';
                return html;
            }
            function reportPreviewHtml(report){
                var meta = report && report.meta && typeof report.meta === 'object' ? report.meta : {};
                var assets = Array.isArray(report.preview_assets) ? report.preview_assets : (Array.isArray(meta.preview_assets) ? meta.preview_assets : []);
                assets = assets.filter(function(asset){ return asset && typeof asset === 'object' && text(asset.url || asset.link || '').trim(); });
                if (!assets.length) return '';
                return '<div class="hpc-gsc-preview-grid">' + assets.map(function(asset){
                    var url = text(asset.url || asset.link || '').trim();
                    var previewUrl = text(asset.preview_url || asset.preview || url).trim();
                    var label = text(asset.label || asset.asset || 'Generated asset').trim();
                    var metaText = text(asset.meta || asset.format || '').trim();
                    var safeUrl = esc(url).replace(/"/g,'&quot;');
                    var safePreview = esc(previewUrl).replace(/"/g,'&quot;');
                    var safeLabel = esc(label).replace(/"/g,'&quot;');
                    return '<a class="hpc-gsc-preview-card" href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">'
                        + '<span class="hpc-gsc-preview-frame"><img src="' + safePreview + '" alt="' + safeLabel + '" loading="lazy"></span>'
                        + '<span class="hpc-gsc-preview-label">' + esc(label) + '</span>'
                        + (metaText ? '<span class="hpc-gsc-preview-meta">' + esc(metaText) + '</span>' : '')
                        + '<span class="hpc-gsc-preview-url">' + esc(url) + '</span>'
                        + '</a>';
                }).join('') + '</div>';
            }
            function reportHtml(report){
                var columns = reportColumns(report), items = Array.isArray(report.items) ? report.items : [];
                var html = '<div class="hpc-gsc-report-card"><div class="hpc-gsc-report-head"><strong>' + esc(report.title || 'Checklist Report') + '</strong>' + (report.summary ? '<span>' + esc(report.summary) + '</span>' : '') + '</div>';
                html += reportExplainHtml(report);
                html += reportPreviewHtml(report);
                if (items.length && columns.length) {
                    html += '<div class="hpc-gsc-report-scroll"><table class="hpc-gsc-report-table"><thead><tr>' + columns.map(function(col){ return '<th>' + esc(col.label || col.key) + '</th>'; }).join('') + '</tr></thead><tbody>';
                    html += items.map(function(item){ return '<tr>' + columns.map(function(col){ return '<td>' + valueHtml(item[col.key], col.key) + '</td>'; }).join('') + '</tr>'; }).join('');
                    html += '</tbody></table></div>';
                }
                html += '</div>';
                return html;
            }
            function renderReports(row, payload){
                var target = reportTarget(row), reports = normalizeReports(payload);
                if (!target) return;
                if (!reports.length) { clearReport(row); return; }
                target.innerHTML = reports.map(reportHtml).join('');
                target.hidden = false;
            }
            function setRowState(row, state, message){
                if (!row) return;
                row.dataset.status = state || 'pending';
                var label = row.querySelector('[data-gsc-state]');
                if (label) label.textContent = message || ({pending:'Pending', running:'Running', success:'Complete', failed:'Failed'}[state] || 'Pending');
            }
            function resetRows(){
                root.querySelectorAll('[data-gsc-item]').forEach(function(row){ setRowState(row, 'pending', 'Pending'); clearReport(row); });
                root.querySelectorAll('[data-hpc-dynamic-button]').forEach(dynamicReset);
                refreshInputState();
            }
            function templateSelect(){ return root.querySelector('[data-gsc-template-select]'); }
            function templateList(){ return root.querySelector('[data-gsc-list]'); }
            function templateSource(templateId){ return root.querySelector('template[data-gsc-template-source="' + css(templateId) + '"]'); }
            function currentTemplateId(){ return root.dataset.currentTemplateId || root.dataset.defaultTemplateId || 'default'; }
            function updateTemplateStatus(){
                var select = templateSelect();
                var status = root.querySelector('[data-gsc-template-status]');
                var description = root.querySelector('[data-gsc-template-description]');
                var option = select ? select.options[select.selectedIndex] : null;
                if (status && option) status.textContent = option.textContent + ' loaded';
                if (description && option) description.textContent = option.dataset.description || '';
            }
            function loadTemplate(templateId){
                var source = templateSource(templateId), list = templateList();
                if (!source || !list) {
                    addLog({level:'error', message:'Checklist template could not be loaded.', context:{template_id:templateId}});
                    return false;
                }
                list.innerHTML = source.innerHTML;
                root.dataset.currentTemplateId = templateId;
                resetRows();
                clearLog();
                updateTemplateStatus();
                addLog({level:'info', message:'Checklist template loaded.', context:{template_id:templateId, step_count:list.querySelectorAll('[data-gsc-step-row]').length}});
                return true;
            }
            function rowInputs(row){
                return row ? Array.prototype.slice.call(row.querySelectorAll('[data-gsc-input]')) : [];
            }
            function rowInputMessages(row, showErrors){
                var messages = [];
                rowInputs(row).forEach(function(input){
                    var value = text(input.value).trim();
                    var type = input.dataset.inputType || input.type || 'text';
                    var label = input.dataset.inputLabel || input.dataset.inputId || 'Input';
                    var required = input.required;
                    var message = '';
                    if (required && !value) {
                        message = label + ' is required.';
                    } else if (value && type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        message = label + ' must be a valid email address.';
                    } else if (value && type === 'number' && !Number.isFinite(Number(value))) {
                        message = label + ' must be a number.';
                    } else if (value && type === 'number' && input.min !== '' && Number(value) < Number(input.min)) {
                        message = label + ' must be at least ' + input.min + '.';
                    } else if (value && type === 'number' && input.max !== '' && Number(value) > Number(input.max)) {
                        message = label + ' must be no more than ' + input.max + '.';
                    } else if (type === 'confirmation' && input.dataset.confirmText) {
                        var expected = text(input.dataset.confirmText);
                        var actual = value;
                        var matches = input.dataset.caseSensitive === '0' ? actual.toLowerCase() === expected.toLowerCase() : actual === expected;
                        if (!matches) message = label + ' must match the required confirmation text.';
                    } else if (value && input.pattern) {
                        try {
                            var pattern = new RegExp('^(?:' + input.pattern + ')$');
                            if (!pattern.test(value)) message = label + ' is invalid.';
                        } catch (error) {}
                    }
                    input.setAttribute('aria-invalid', message ? 'true' : 'false');
                    var errorNode = input.closest('.hpc-gsc-input-field')?.querySelector('[data-gsc-input-error]');
                    if (errorNode) errorNode.textContent = showErrors ? message : '';
                    if (message) messages.push(message);
                });
                return messages;
            }
            function validateRowInputs(row, showErrors){
                return rowInputMessages(row, showErrors).length === 0;
            }
            function rowAndChildrenInputMessages(stepRow, showErrors){
                var messages = rowInputMessages(stepRow, showErrors);
                var card = stepRow ? root.querySelector('[data-gsc-step-card][data-step-id="' + css(stepRow.dataset.stepId || '') + '"]') : null;
                var childRows = card ? Array.prototype.slice.call(card.querySelectorAll('[data-gsc-subtask-row]')) : [];
                childRows.forEach(function(childRow){
                    messages = messages.concat(rowInputMessages(childRow, showErrors));
                });
                return messages;
            }
            function disabledReason(messages){
                messages = (messages || []).filter(Boolean);
                if (!messages.length) return '';
                return 'Can not run yet: ' + messages.join(' ');
            }
            function setButtonBlocked(button, messages){
                if (!button) return;
                var reason = disabledReason(messages);
                button.disabled = !!reason;
                setDisabledReason(button, reason);
            }
            function collectRowInputs(row){
                var values = {};
                rowInputs(row).forEach(function(input){
                    values[input.dataset.inputId || input.name || input.id] = text(input.value).trim();
                });
                return values;
            }
            function rowAndChildrenInputsValid(stepRow, showErrors){
                return rowAndChildrenInputMessages(stepRow, showErrors).length === 0;
            }
            function refreshInputState(){
                root.querySelectorAll('[data-gsc-item]').forEach(function(row){
                    var rowMessages = rowInputMessages(row, false);
                    var rowButton = row.querySelector('[data-gsc-run-item]');
                    setButtonBlocked(rowButton, rowMessages);
                });
                root.querySelectorAll('[data-gsc-step-row]').forEach(function(stepRow){
                    var stepButton = stepRow.querySelector('[data-gsc-run-step]');
                    setButtonBlocked(stepButton, rowAndChildrenInputMessages(stepRow, false));
                });
                var runAllButton = root.querySelector('[data-gsc-run-all]');
                if (runAllButton) {
                    var allMessages = [];
                    root.querySelectorAll('[data-gsc-step-row]').forEach(function(stepRow){
                        allMessages = allMessages.concat(rowAndChildrenInputMessages(stepRow, false));
                    });
                    setButtonBlocked(runAllButton, allMessages);
                }
            }
            function postItem(stepId, subtaskId, inputs){
                var body = new URLSearchParams();
                body.set('action', root.dataset.runAction || '');
                body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                body.set('step_id', stepId || '');
                body.set('subtask_id', subtaskId || '');
                body.set('template_id', currentTemplateId());
                Object.keys(inputs || {}).forEach(function(key){
                    body.set('inputs[' + key + ']', inputs[key]);
                });
                return fetch(root.dataset.ajaxUrl || window.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                }).then(function(response){ return response.json(); }).then(function(payload){
                    if (!payload || !payload.success) {
                        var message = payload && payload.data && (payload.data.message || payload.data.error) ? (payload.data.message || payload.data.error) : 'AJAX request failed.';
                        throw new Error(message);
                    }
                    return payload.data || {};
                });
            }
            function postAction(action, payload){
                var body = new URLSearchParams();
                body.set('action', action || '');
                body.set(root.dataset.nonceField || 'nonce', root.dataset.nonce || '');
                Object.keys(payload || {}).forEach(function(key){
                    var value = payload[key];
                    if (Array.isArray(value)) {
                        value.forEach(function(item){ body.append(key + '[]', item); });
                    } else {
                        body.set(key, value);
                    }
                });
                return fetch(root.dataset.ajaxUrl || window.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                }).then(function(response){ return response.json(); }).then(function(payload){
                    if (!payload || !payload.success) {
                        var message = payload && payload.data && (payload.data.message || payload.data.error) ? (payload.data.message || payload.data.error) : 'AJAX request failed.';
                        throw new Error(message);
                    }
                    return payload.data || {};
                });
            }
            var extensionApi = {
                root: root,
                text: text,
                escapeHtml: esc,
                cssSelectorValue: css,
                currentTemplateId: currentTemplateId,
                collectRowInputs: collectRowInputs,
                validateRowInputs: validateRowInputs,
                setRowState: setRowState,
                reportTarget: reportTarget,
                clearReport: clearReport,
                renderReports: renderReports,
                addLog: addLog,
                addLogs: addLogs,
                refreshInputState: refreshInputState,
                postAction: postAction
            };
            root.hexaChecklistApi = extensionApi;
            root.dispatchEvent(new CustomEvent('hexa:checklist:ready', {detail:{api:extensionApi}}));
            function runExtension(row, scope){
                var detail = {
                    api: extensionApi,
                    row: row,
                    scope: scope,
                    stepId: row ? row.dataset.stepId || '' : '',
                    subtaskId: row ? row.dataset.subtaskId || '' : '',
                    handled: false,
                    promise: null
                };
                root.dispatchEvent(new CustomEvent('hexa:checklist:run', {detail:detail}));
                if (!detail.handled) return null;
                return Promise.resolve(detail.promise).then(function(result){ return result !== false; }).catch(function(error){
                    setRowState(row, 'failed', error && error.message ? error.message : 'Failed');
                    addLog({level:'error', message:error && error.message ? error.message : 'Checklist workflow extension failed.', context:{step_id:detail.stepId, subtask_id:detail.subtaskId}});
                    return false;
                });
            }
            function runItem(row){
                var stepId = row ? row.dataset.stepId : '';
                var subtaskId = row ? row.dataset.subtaskId : '';
                if (!validateRowInputs(row, true)) {
                    setRowState(row, 'failed', 'Needs Input');
                    addLog({level:'error', message:'Required input missing or invalid.', context:{step_id:stepId, subtask_id:subtaskId}});
                    refreshInputState();
                    return Promise.resolve(false);
                }
                var extension = runExtension(row, 'item');
                if (extension) return extension;
                setRowState(row, 'running', 'Running');
                clearReport(row);
                return postItem(stepId, subtaskId, collectRowInputs(row)).then(function(data){
                    addLogs(data.logs);
                    renderReports(row, data.data || data);
                    if (data.success) {
                        setRowState(row, 'success', data.message || 'Complete');
                        return true;
                    }
                    setRowState(row, 'failed', data.message || 'Failed');
                    return false;
                }).catch(function(error){
                    setRowState(row, 'failed', error.message || 'Failed');
                    addLog({level:'error', message:error.message || 'Checklist item failed.', context:{step_id:stepId, subtask_id:subtaskId}});
                    return false;
                });
            }
            function runParentAction(stepRow){
                var stepId = stepRow ? stepRow.dataset.stepId : '';
                if (!validateRowInputs(stepRow, true)) {
                    addLog({level:'error', message:'Required parent-step input missing or invalid.', context:{step_id:stepId}});
                    return Promise.resolve(false);
                }
                return postItem(stepId, '', collectRowInputs(stepRow)).then(function(data){
                    addLogs(data.logs);
                    if (!data.success) {
                        addLog({level:'error', message:data.message || 'Parent step action failed.', context:{step_id:stepId}});
                    }
                    return !!data.success;
                }).catch(function(error){
                    addLog({level:'error', message:error.message || 'Parent step action failed.', context:{step_id:stepId}});
                    return false;
                });
            }
            async function runStep(stepRow){
                if (!stepRow) return false;
                var stepId = stepRow.dataset.stepId || '';
                var card = root.querySelector('[data-gsc-step-card][data-step-id="' + css(stepId) + '"]');
                var subtasks = card ? Array.prototype.slice.call(card.querySelectorAll('[data-gsc-subtask-row]')) : [];
                if (!rowAndChildrenInputsValid(stepRow, true)) {
                    setRowState(stepRow, 'failed', 'Needs Input');
                    addLog({level:'error', message:'Required input missing or invalid for this step.', context:{step_id:stepId}});
                    refreshInputState();
                    return false;
                }
                var extension = runExtension(stepRow, 'step');
                if (extension) return extension;
                if (!subtasks.length) {
                    return runItem(stepRow);
                }
                setRowState(stepRow, 'running', 'Running subtasks');
                addLog({level:'info', message:'Starting parent step subtasks.', context:{step_id:stepId, subtask_count:subtasks.length}});
                var allOk = true;
                if (stepRow.dataset.hasAction === '1') {
                    allOk = await runParentAction(stepRow);
                    if (!allOk) {
                        setRowState(stepRow, 'failed', 'Parent action failed');
                        return false;
                    }
                }
                for (var i = 0; i < subtasks.length; i++) {
                    var ok = await runItem(subtasks[i]);
                    if (!ok) allOk = false;
                }
                setRowState(stepRow, allOk ? 'success' : 'failed', allOk ? 'All subtasks complete' : 'Subtask failed');
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Parent step completed.' : 'Parent step completed with failures.', context:{step_id:stepId}});
                return allOk;
            }
            async function runAll(button){
                resetRows();
                clearLog();
                dynamicStart(button, 'Running...');
                addLog({level:'info', message:'Starting full getting started checklist run.', context:{steps:root.querySelectorAll('[data-gsc-step-row]').length}});
                var rows = Array.prototype.slice.call(root.querySelectorAll('[data-gsc-step-row]'));
                var allOk = true;
                for (var i = 0; i < rows.length; i++) {
                    var ok = await runStep(rows[i]);
                    if (!ok) allOk = false;
                }
                addLog({level:allOk ? 'success' : 'error', message:allOk ? 'Checklist run finished successfully.' : 'Checklist run finished with failures.', context:{}});
                if (allOk) dynamicSuccess(button, 'Checklist Finished'); else dynamicError(button, 'Checklist Failed');
            }
            root.addEventListener('click', function(event){
                var clear = event.target.closest('[data-gsc-clear-log]');
                if (clear) {
                    event.preventDefault();
                    event.stopPropagation();
                    clearLog();
                    return;
                }
                var reset = event.target.closest('[data-gsc-reset]');
                if (reset) {
                    event.preventDefault();
                    resetRows();
                    clearLog();
                    return;
                }
                var loadTemplateButton = event.target.closest('[data-gsc-load-template]');
                if (loadTemplateButton) {
                    event.preventDefault();
                    var select = templateSelect();
                    loadTemplate(select ? select.value : root.dataset.defaultTemplateId || 'default');
                    return;
                }
                var runAllButton = event.target.closest('[data-gsc-run-all]');
                if (runAllButton) {
                    event.preventDefault();
                    runAll(runAllButton);
                    return;
                }
                var runStepButton = event.target.closest('[data-gsc-run-step]');
                if (runStepButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runStepButton, 'Running...');
                    var stepRow = root.querySelector('[data-gsc-step-row][data-step-id="' + css(runStepButton.dataset.stepId || '') + '"]');
                    if (!rowAndChildrenInputsValid(stepRow, true)) {
                        setRowState(stepRow, 'failed', 'Needs Input');
                        addLog({level:'error', message:'Required input missing or invalid for this step.', context:{step_id:runStepButton.dataset.stepId || ''}});
                        refreshInputState();
                        dynamicError(runStepButton, 'Needs Input');
                        return;
                    }
                    runStep(stepRow).then(function(ok){ if (ok) dynamicSuccess(runStepButton, 'Done'); else dynamicError(runStepButton, 'Failed'); });
                    return;
                }
                var runItemButton = event.target.closest('[data-gsc-run-item]');
                if (runItemButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    dynamicStart(runItemButton, 'Running...');
                    var selector = '[data-gsc-subtask-row][data-step-id="' + css(runItemButton.dataset.stepId || '') + '"][data-subtask-id="' + css(runItemButton.dataset.subtaskId || '') + '"]';
                    var row = root.querySelector(selector);
                    if (!validateRowInputs(row, true)) {
                        setRowState(row, 'failed', 'Needs Input');
                        addLog({level:'error', message:'Required input missing or invalid.', context:{step_id:runItemButton.dataset.stepId || '', subtask_id:runItemButton.dataset.subtaskId || ''}});
                        refreshInputState();
                        dynamicError(runItemButton, 'Needs Input');
                        return;
                    }
                    runItem(row).then(function(ok){ if (ok) dynamicSuccess(runItemButton, 'Done'); else dynamicError(runItemButton, 'Failed'); });
                }
            });
            root.addEventListener('input', function(event){
                if (!event.target.closest('[data-gsc-input]')) return;
                refreshInputState();
            });
            root.addEventListener('change', function(event){
                if (!event.target.closest('[data-gsc-template-select]')) return;
                updateTemplateStatus();
            });
            root.addEventListener('click', function(event){
                if (event.target.closest('[data-gsc-input]')) {
                    event.stopPropagation();
                }
            }, true);
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function(){ updateTemplateStatus(); refreshInputState(); }, {once:true});
            } else {
                window.setTimeout(function(){ updateTemplateStatus(); refreshInputState(); }, 0);
            }
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function status_icon(): string {
        return '<span class="hpc-gsc-status-icon" aria-hidden="true">'
            . '<span class="hpc-gsc-icon-pending"></span>'
            . '<span class="hpc-gsc-icon-spinner"></span>'
            . '<span class="hpc-gsc-icon-check"><svg viewBox="0 0 512 512" focusable="false"><path d="M470.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L192 338.7 425.4 105.4c12.5-12.5 32.8-12.5 45.2 0z"></path></svg></span>'
            . '<span class="hpc-gsc-icon-x"><svg viewBox="0 0 384 512" focusable="false"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3l105.4 105.3c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256l105.3-105.4z"></path></svg></span>'
            . '</span>';
    }

    private function type_label( string $type ): string {
        return match ( $type ) {
            GettingStartedChecklistStep::TYPE_STATUS_CHECK => 'Status Check',
            GettingStartedChecklistStep::TYPE_SETUP_ACTION => 'Setup Action',
            GettingStartedChecklistStep::TYPE_FEATURE_TOGGLE => 'Feature Toggle',
            GettingStartedChecklistStep::TYPE_CONFIG_MUTATION => 'Config Mutation',
            GettingStartedChecklistStep::TYPE_AJAX_REQUEST => 'AJAX Request',
            GettingStartedChecklistStep::TYPE_CUSTOM => 'Custom',
            default => 'Callback',
        };
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function log_row( array $entry ): string {
        $level   = strtolower( (string) ( $entry['level'] ?? 'info' ) );
        $context = isset( $entry['context'] ) && is_array( $entry['context'] ) && [] !== $entry['context']
            ? wp_json_encode( $entry['context'], JSON_PRETTY_PRINT )
            : '';

        return '<div class="hpc-gsc-log-row">'
            . '<div class="hpc-gsc-log-time">' . esc_html( (string) ( $entry['time'] ?? '' ) ) . '</div>'
            . '<div><span class="hpc-gsc-log-level ' . esc_attr( $level ) . '">' . esc_html( $level ) . '</span></div>'
            . '<div><div class="hpc-gsc-log-message">' . esc_html( (string) ( $entry['message'] ?? '' ) ) . '</div>'
            . ( '' !== $context ? '<div class="hpc-gsc-log-context">' . esc_html( $context ) . '</div>' : '' )
            . '</div></div>';
    }
}
