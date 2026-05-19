<?php
$vars = get_defined_vars();
$modal = isset($vars['modal']) && is_array($vars['modal']) ? $vars['modal'] : [];
$options = isset($vars['options']) && is_array($vars['options']) ? $vars['options'] : [];
$default_options = isset($vars['default_options']) && is_array($vars['default_options']) ? $vars['default_options'] : [];
$mail_placeholders = isset($vars['mail_placeholders']) && is_array($vars['mail_placeholders']) ? $vars['mail_placeholders'] : [];
?>
<div class="rs-mail-modal" data-rs-mail-modal="<?php echo esc_attr((string) ($modal['modal_id'] ?? '')); ?>" hidden>
    <div class="rs-mail-modal__panel">
        <button type="button" class="button rs-mail-modal__close" data-rs-close-mail-modal aria-label="<?php esc_attr_e('Schließen', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?>">&times;</button>
        <div class="rs-mail-modal__intro">
            <h2><?php echo esc_html((string) ($modal['title'] ?? '')); ?></h2>
            <p><?php echo esc_html((string) ($modal['description'] ?? '')); ?></p>
        </div>

        <?php foreach ((array) ($modal['shared_fields'] ?? []) as $field) : ?>
            <?php $this->render_mail_template_shared_field($field, $options); ?>
        <?php endforeach; ?>

        <?php foreach ((array) ($modal['sections'] ?? []) as $section) : ?>
            <?php $this->render_mail_template_section($section, $options, $default_options); ?>
        <?php endforeach; ?>

        <div class="rs-mail-modal__footer">
            <p class="description"><?php esc_html_e('Per Klick wird der Platzhalter in das zuletzt fokussierte Feld oder in den aktiven Editor eingefügt.', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></p>
            <div class="rs-mail-placeholder-list">
                <?php foreach ($mail_placeholders as $placeholder) : ?>
                    <button type="button" class="button button-secondary" data-rs-insert-placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_html($placeholder); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
