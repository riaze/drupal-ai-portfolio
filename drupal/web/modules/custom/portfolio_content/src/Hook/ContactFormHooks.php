<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\portfolio_content\Import\ContentImporter;

/**
 * Contact form: privacy notice at the point of collection, input purposes.
 */
final class ContactFormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for the site-wide contact form.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_contact_message_feedback_form_alter')]
  public function alter(array &$form, FormStateInterface $form_state): void {
    // A preview step adds nothing to a plain-text message.
    $form['actions']['preview']['#access'] = FALSE;
    // WCAG 1.3.5: identify the purpose of personal data inputs.
    $form['name']['#attributes']['autocomplete'] = 'name';
    $form['mail']['#attributes']['autocomplete'] = 'email';

    $notice = $this->t('Your details are only used to reply to your message.');
    $policy = $this->privacyPolicy();
    if ($policy !== NULL) {
      $notice = $this->t('Your details are only used to reply to your message. See the <a href=":url">privacy policy</a>.', [
        ':url' => $policy->toUrl()->toString(),
      ]);
    }
    $form['privacy_notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $notice,
      '#attributes' => ['class' => ['privacy-notice']],
      '#weight' => 99,
      '#cache' => ['tags' => ['node_list']],
    ];
  }

  /**
   * Returns the published privacy policy in the current language, if any.
   */
  private function privacyPolicy(): ?NodeInterface {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => ContentImporter::uuid('node:privacy-policy')]);
    $node = reset($nodes);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $node = $node->hasTranslation($langcode) ? $node->getTranslation($langcode) : $node;
    return $node->isPublished() ? $node : NULL;
  }

}
