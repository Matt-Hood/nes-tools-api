<?php

namespace Drupal\hf_toolkit_rest_api_subs\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a HF Toolkit Key Resource.
 *
 * @RestResource(
 *   id = "subscription_resource",
 *   label = @Translation("HF Toolkit Access Key Resource"),
 *   uri_paths = {
 *     "canonical" = "/hf_toolkit_rest_api/toolkit_sub_resource/{$access_key}"
 *   }
 * )
 */
class SubscriptionResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get($access_key = ''): ResourceResponse {
    // @todo Look into seeing if I can load/match the access key here with a public method from Drupal
    $all_access_keys = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => '1',
        'type' => 'access_keys',
      ]);

    $access_key_data = [];
    if (!empty($all_access_keys)) {
      /*
      Get the details of each access key node and
      puts it in an array.
      We have to do this because we need to manipulate
      the array so that it will spit out exactly the XML we want
       */

      // @todo improve speed of search
      foreach ($all_access_keys as $key) {
        $valid_spin_key = '';
        if (!is_null($key->get('field_subscription_type')->referencedEntities()[0])) {
          $valid_spin_key = $key->get('field_subscription_type')->referencedEntities()[0]->getName() ?? '';
        }
        if ($valid_spin_key == 'HF Toolkit Subscription Time') {
          $valid_key = $key->get('field_access_key_')->getValue();
          $creation_date = $key->getCreatedTime();
          if ($valid_key[0]['value'] == $access_key) {
            $access_key_data = [
              "key" => $valid_key[0]['key'],
              "value" => $valid_key[0]['value'],
            ];
            $redeem_date = date("y-m-d h:i:s");
            ;
            // Unpublish the node.
            $key->set('field_state', '[REDEEMED]');
            $key->setUnpublished();
            $key->save();
            // After access key is found exit the loop and return.
            break;
          }
        }
      }
    }

    if (!empty($access_key_data)) {
      $response = [
        'message' => 'Successfully redeemed accesss key' . ' ' . $access_key_data["value"],
        'subscription' => $access_key_data["key"],
        'redeemed' => $redeem_date ?? '',
      ];
    }
    else {
      $response = ['message' => 'invalid key'];
    }
    return new ResourceResponse($response);
  }

}
