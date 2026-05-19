<?php
$vars = get_defined_vars();
$section = isset($vars['section']) && is_array($vars['section']) ? $vars['section'] : [];
$options = isset($vars['options']) && is_array($vars['options']) ? $vars['options'] : [];
$default_options = isset($vars['default_options']) && is_array($vars['default_options']) ? $vars['default_options'] : [];
$subject_key = isset($vars['subject_key']) ? (string) $vars['subject_key'] : '';
$text_key = isset($vars['text_key']) ? (string) $vars['text_key'] : '';
$html_key = isset($vars['html_key']) ? (string) $vars['html_key'] : '';
$html_editor_id = isset($vars['html_editor_id']) ? (string) $vars['html_editor_id'] : '';
$tab_group = isset($vars['tab_group']) ? (string) $vars['tab_group'] : '';
?>
<section class="rs-mail-modal__section" data-rs-mail-template-section>
    <h3><?php echo esc_html((string) ($section['title'] ?? '')); ?></h3>

    <div class="rs-mail-modal__checks">
        <?php foreach ((array) ($section['toggles'] ?? []) as $toggle) : ?>
            <?php $this->render_mail_template_toggle($toggle, $options); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($subject_key !== '') : ?>
        <div class="rs-mail-modal__field">
            <label for="<?php echo esc_attr($subject_key); ?>"><?php esc_html_e('Betreff', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></label>
            <input class="regular-text" id="<?php echo esc_attr($subject_key); ?>" type="text" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($subject_key); ?>]" value="<?php echo esc_attr((string) ($options[$subject_key] ?? '')); ?>">
        </div>
    <?php endif; ?>

    <?php if ($tab_group !== '') : ?>
        <div class="rs-mail-template-tabs">
            <?php if ($html_key !== '' && $html_editor_id !== '') : ?>
                <button type="button" class="button button-secondary is-active" data-rs-mail-tab="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="html"><?php esc_html_e('Visuell', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
                <button type="button" class="button button-secondary" data-rs-mail-tab="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="code"><?php esc_html_e('Code', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
            <?php endif; ?>
            <?php if ($text_key !== '') : ?>
                <button type="button" class="button button-secondary<?php echo ($html_key === '' || $html_editor_id === '') ? ' is-active' : ''; ?>" data-rs-mail-tab="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="text"><?php esc_html_e('Text', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
            <?php endif; ?>
            <button
                type="button"
                class="button button-secondary"
                data-rs-mail-reset-template
                data-rs-mail-subject-key="<?php echo esc_attr($subject_key); ?>"
                data-rs-mail-text-key="<?php echo esc_attr($text_key); ?>"
                data-rs-mail-html-key="<?php echo esc_attr($html_key); ?>"
                data-rs-mail-html-editor-id="<?php echo esc_attr($html_editor_id); ?>"
            ><?php esc_html_e('Reset', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></button>
        </div>
    <?php endif; ?>

    <div hidden>
        <?php if ($subject_key !== '') : ?>
            <textarea data-rs-mail-default-key="<?php echo esc_attr($subject_key); ?>"><?php echo esc_textarea((string) ($default_options[$subject_key] ?? '')); ?></textarea>
        <?php endif; ?>
        <?php if ($html_key !== '') : ?>
            <textarea data-rs-mail-default-key="<?php echo esc_attr($html_key); ?>"><?php echo esc_textarea((string) ($default_options[$html_key] ?? '')); ?></textarea>
        <?php endif; ?>
        <?php if ($text_key !== '') : ?>
            <textarea data-rs-mail-default-key="<?php echo esc_attr($text_key); ?>"><?php echo esc_textarea((string) ($default_options[$text_key] ?? '')); ?></textarea>
        <?php endif; ?>
    </div>

    <?php if ($html_key !== '' && $html_editor_id !== '') : ?>
        <div class="rs-mail-template-panel" data-rs-mail-tab-panel="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="html">
            <div class="rs-mail-modal__field rs-mail-editor-cell">
                <span><?php esc_html_e('Visuelle Version', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></span>
                <textarea class="large-text code rs-mail-html-textarea" id="<?php echo esc_attr($html_editor_id); ?>" rows="12" data-rs-mail-html-editor name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($html_key); ?>]"><?php echo esc_textarea((string) ($options[$html_key] ?? '')); ?></textarea>
                <?php if (!empty($section['html_help'])) : ?>
                    <p class="description"><?php echo esc_html((string) $section['html_help']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="rs-mail-template-panel" data-rs-mail-tab-panel="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="code" hidden>
            <div class="rs-mail-modal__field">
                <label for="<?php echo esc_attr($html_editor_id . '_code'); ?>"><?php esc_html_e('Code-Version', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></label>
                <textarea class="large-text code" id="<?php echo esc_attr($html_editor_id . '_code'); ?>" rows="12" data-rs-mail-html-code-for="<?php echo esc_attr($html_editor_id); ?>"><?php echo esc_textarea((string) ($options[$html_key] ?? '')); ?></textarea>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($text_key !== '') : ?>
        <div class="rs-mail-template-panel" data-rs-mail-tab-panel="<?php echo esc_attr($tab_group); ?>" data-rs-mail-panel="text"<?php echo ($html_key !== '' && $html_editor_id !== '') ? ' hidden' : ''; ?>>
            <div class="rs-mail-modal__field">
                <label for="<?php echo esc_attr($text_key); ?>"><?php esc_html_e('Text-Version', Restatify_Booking_Assistant_Constants::TEXT_DOMAIN); ?></label>
                <textarea class="large-text code" id="<?php echo esc_attr($text_key); ?>" rows="8" name="<?php echo esc_attr(Restatify_Booking_Assistant_Constants::OPTION_KEY); ?>[<?php echo esc_attr($text_key); ?>]"><?php echo esc_textarea((string) ($options[$text_key] ?? '')); ?></textarea>
                <?php if (!empty($section['text_help'])) : ?>
                    <p class="description"><?php echo esc_html((string) $section['text_help']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
