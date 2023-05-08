<?php

namespace Drupal\ghost\Commands;

use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

/**
 * Class KeyGenerator.
 *
 * Commands for generating access key nodes.
 */
class KeyGenerator extends DrushCommands {

  // ... rest of the code ...

  /**
   * Generate a random access key.
   *
   * @return string
   */
  protected function generateAccessKey() {
    $uuid_parts = [
      strtoupper(bin2hex(random_bytes(4))),
      strtoupper(bin2hex(random_bytes(2))),
      strtoupper(bin2hex(random_bytes(2))),
      strtoupper(bin2hex(random_bytes(2))),
      strtoupper(bin2hex(random_bytes(6))),
    ];

    return 'GHOST-' . implode('-', $uuid_parts);
  }

  /**
   * Creates a specified number of nodes with the specified fields.
   *
   * @param string $title_key
   *   The title key to use from the title options array (e.g., 'Day Key', 'Week Key', 'Month Key').
   * @param int $count
   *   The number of nodes to create.
   * @param string $state
   *   The state (default: 'Active').
   * @param int $subscription_type
   *   The subscription type entity reference ID (default: 5).
   *
   * @command ghost:create-nodes
   *
   * @option content-type The content type of the nodes to be created. Defaults to 'access_keys'.
   * @usage drush ghost:create-nodes 'Day Key' 10
   *   Creates 10 nodes with the specified access key code, state, and subscription type entity reference ID using the 'Day Key' title key.
   */
  public function createNodes($title_key, $count, $state = 'Active', $subscription_type = 5, $options = ['content-type' => 'access_keys']) {
    $content_type = $options['content-type'];
    $generated_keys = [];
    $title_options = ['Day Access', 'Week Access', 'Month Access'];
    $key_options = ['Day Key', 'Week Key', 'Month Key'];
    $key_index = array_search($title_key, $title_options);
    if ($key_index === FALSE) {
      $this->logger()->error(dt('Invalid title key: %title_key', ['%title_key' => $title_key]));
      return;
    }

    for ($i = 0; $i < $count; $i++) {
      $generated_key = $this->generateAccessKey();
      $generated_keys[] = $generated_key;
      $access_key = [
        [
          'key' => $key_options[$key_index],
          'value' => $generated_key,
          'description' => '',
        ],
      ];
      $node = Node::create([
        'type' => $content_type,
        'title' => $title_key,
        'field_access_key_' => $access_key,
        'field_state' => $state,
        'field_subscription_type' => [
          'target_id' => $subscription_type,
        ],
      ]);

      $node->save();

      $this->logger()->success(dt('Node with title %title has been created with key %key.', [
        '%title' => $node->label(),
        '%key' => $generated_key,
      ]));
      \Drupal::logger('ghost')->notice('Node with title %title has been created with key %key.', [
        '%title' => $node->label(),
        '%key' => $generated_key,
      ]);
    }

    $summary = implode("\n", $generated_keys);
    $this->logger()->notice(dt("Summary of generated key values:\n\n%summary", [
      '%summary' => $summary,
    ]));
    \Drupal::logger('ghost')->notice('Summary of generated key values: %summary', [
      '%summary' => $summary,
    ]);
  }

}
