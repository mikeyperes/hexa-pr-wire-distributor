<?php

namespace Hexa\PluginCore\FaqSets;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

final class FaqSetManager {
    public function sanitizeSets( array $sets ): array {
        $sanitized = [];

        foreach ( $sets as $set ) {
            if ( ! is_array( $set ) ) {
                continue;
            }

            $items = $this->normalizeItems( $set["items"] ?? [] );
            $name  = $this->sanitizeText( (string) ( $set["name"] ?? "" ) );
            $slug  = $this->sanitizeKey( (string) ( $set["slug"] ?? "faq-set-" . count( $sanitized ) ) );

            if ( "" === $name && empty( $items ) ) {
                continue;
            }

            $sanitized[] = [
                "name"  => $name,
                "slug"  => "" !== $slug ? $slug : "faq-set-" . count( $sanitized ),
                "items" => $items,
            ];
        }

        return $sanitized;
    }

    public function normalizeItems( $items ): array {
        if ( ! is_array( $items ) ) {
            return [];
        }

        $normalized = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $question = trim( (string) ( $item["question"] ?? $item["title"] ?? "" ) );
            $answer   = trim( (string) ( $item["answer"] ?? $item["content"] ?? "" ) );

            if ( "" === $question || "" === $answer ) {
                continue;
            }

            $normalized[] = [
                "question" => $this->sanitizeText( $question ),
                "answer"   => $this->sanitizePostHtml( $answer ),
            ];
        }

        return $normalized;
    }

    public function resolveSet( array $sets, string $slug, string $primary_slug = "" ): ?array {
        if ( "primary" === $slug ) {
            if ( "" !== $primary_slug ) {
                foreach ( $sets as $set ) {
                    if ( is_array( $set ) && (string) ( $set["slug"] ?? "" ) === $primary_slug ) {
                        return $set;
                    }
                }
            }
            return ! empty( $sets ) && is_array( $sets[0] ) ? $sets[0] : null;
        }

        foreach ( $sets as $set ) {
            if ( is_array( $set ) && (string) ( $set["slug"] ?? "" ) === $slug ) {
                return $set;
            }
        }

        return null;
    }

    public function answerHtml( string $answer ): string {
        $html = $this->sanitizePostHtml( $answer );

        return (string) preg_replace_callback(
            "/<a\\b([^>]*)>/i",
            static function ( array $matches ): string {
                $attrs = rtrim( (string) ( $matches[1] ?? "" ) );
                if ( false === stripos( $attrs, " target=" ) ) {
                    $attrs .= " target=\"_blank\"";
                }
                if ( false === stripos( $attrs, " rel=" ) ) {
                    $attrs .= " rel=\"noopener noreferrer\"";
                }
                return "<a" . $attrs . ">";
            },
            $html
        );
    }

    public function buildSchema( $items ): array {
        $items = $this->normalizeItems( $items );
        $schema = [
            "@context"   => "https://schema.org",
            "@type"      => "FAQPage",
            "mainEntity" => [],
        ];

        foreach ( $items as $item ) {
            $schema["mainEntity"][] = [
                "@type"          => "Question",
                "name"           => $item["question"],
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text"  => $this->stripAllTags( $item["answer"] ),
                ],
            ];
        }

        return $schema;
    }

    public function renderSchemaScript( $items ): string {
        $schema = $this->buildSchema( $items );
        if ( empty( $schema["mainEntity"] ) ) {
            return "";
        }

        return "<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>";
    }

    public function renderFaqs( array $set, array $args = [] ): string {
        $items = $this->normalizeItems( $set["items"] ?? [] );
        if ( empty( $items ) ) {
            return "";
        }

        ob_start();
        CoreUi::render_assets();
        $assets = (string) ob_get_clean();

        $style         = isset( $args["style"] ) ? (string) $args["style"] : "accordion";
        $inject_schema = array_key_exists( "inject_schema", $args ) ? (bool) $args["inject_schema"] : false;
        $slug          = isset( $set["slug"] ) ? (string) $set["slug"] : "faq";
        $class         = "hpc-faqs hpc-faqs-" . sanitize_html_class( $style );

        ob_start();
        ?>
        <?php echo $assets; ?>
        <?php echo $this->styles(); ?>
        <div class="<?php echo esc_attr( $class ); ?>" data-faq-set="<?php echo esc_attr( $slug ); ?>">
            <?php foreach ( $items as $index => $item ) : ?>
                <?php if ( "list" === $style ) : ?>
                    <article class="hpc-faq-list-item">
                        <h4><?php echo esc_html( $item["question"] ); ?></h4>
                        <div><?php echo $this->answerHtml( $item["answer"] ); ?></div>
                    </article>
                <?php else : ?>
                    <details class="hpc-faq-item"<?php echo 0 === $index && ! empty( $args["first_open"] ) ? " open" : "" ; ?>>
                        <summary><?php echo esc_html( $item["question"] ); ?></summary>
                        <div class="hpc-faq-answer"><?php echo $this->answerHtml( $item["answer"] ); ?></div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        $html = (string) ob_get_clean();

        if ( $inject_schema ) {
            $html .= $this->renderSchemaScript( $items );
        }

        return $html;
    }

    private function styles(): string {
        ob_start();
        ?>
        <style>
            .hpc-faqs{display:grid;gap:10px;margin:14px 0}.hpc-faq-item{background:#fff;border:1px solid #d9e0ea;border-radius:8px;overflow:hidden}.hpc-faq-item summary{align-items:center;color:#172033;cursor:pointer;display:flex;font-weight:800;gap:10px;justify-content:space-between;line-height:1.35;padding:14px 16px}.hpc-faq-item summary::-webkit-details-marker{display:none}.hpc-faq-item summary:after{color:#65758b;content:"+";font-size:20px;line-height:1}.hpc-faq-item[open] summary{border-bottom:1px solid #e5eaf1}.hpc-faq-item[open] summary:after{content:"-"}.hpc-faq-answer{color:#3f4d63;line-height:1.65;padding:14px 16px}.hpc-faq-answer p:first-child{margin-top:0}.hpc-faq-answer p:last-child{margin-bottom:0}.hpc-faq-list-item{background:#fff;border:1px solid #d9e0ea;border-radius:8px;padding:14px 16px}.hpc-faq-list-item h4{font-size:15px;margin:0 0 8px}.hpc-faq-list-item div{color:#3f4d63;line-height:1.65}
        </style>
        <?php
        return (string) ob_get_clean();
    }

    private function sanitizeText( string $value ): string {
        if ( function_exists( "sanitize_text_field" ) ) {
            return sanitize_text_field( $value );
        }
        return trim( strip_tags( $value ) );
    }

    private function sanitizeKey( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }
        return strtolower( preg_replace( "/[^a-zA-Z0-9_\-]/", "", $value ) );
    }

    private function sanitizePostHtml( string $value ): string {
        if ( function_exists( "wp_kses_post" ) ) {
            return wp_kses_post( $value );
        }
        return $value;
    }

    private function stripAllTags( string $value ): string {
        if ( function_exists( "wp_strip_all_tags" ) ) {
            return wp_strip_all_tags( $value );
        }
        return trim( strip_tags( $value ) );
    }
}
