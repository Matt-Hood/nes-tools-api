<?php

namespace Drupal\hf_toolkit_rest_api\Plugin\rest\resource;

use Drupal\user\Entity\User;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a Spin Access Key Resource.
 *
 * @RestResource(
 *   id = "redeem_spin_resource",
 *   label = @Translation("Redeem Spins"),
 *   uri_paths = {
 *     "canonical" = "/hf_toolkit_rest_api/redeem_spin_resource/{spin_key}"
 *   }
 * )
 */
class RedeemSpins extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get($spin_key = ''): ResourceResponse {
    // @todo Look into seeing if I can load/match the access key here with a public method from Drupal
    $all_access_keys = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => '1',
        'type' => 'access_keys',
      ]);

    $access_key_uuid_data = explode("-", $spin_key);
    $uid = $access_key_uuid_data[0];
    $spin_key = $access_key_uuid_data[1];

    $spin_balance_data = [];

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

        if ($valid_spin_key == 'Spin Balance') {

          $valid_key = $key->get('field_access_key_')->getValue();
          $creation_date = $key->getCreatedTime();
          if ($valid_key[0]['value'] == $spin_key) {
            $account = User::load($uid);
            $spin_balance = $account->get('field_spin_balance')->getValue();
            if (empty($spin_balance)) {
              $spin_balance = 0;

            }
            $spin_balance_value = intval($spin_balance[0]['value']);
            $spins_bought = $valid_key[0]['key'];
            $user_updated_spin_balance = $spin_balance_value + intval($spins_bought);
            $account->set('field_spin_balance', $user_updated_spin_balance);
            $account->save();
            $spin_balance_data = [
              "key" => $valid_key[0]['key'],
              "value" => $valid_key[0]['value'],
              "bought" => $spins_bought,
              "balance" => $user_updated_spin_balance,
            ];
            $redeem_date = date("y-m-d h:i:s");

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

    if (!empty($spin_balance_data)) {
      $response = [
        'message' => 'Successfully redeemed accesss key' . ' ' . $spin_balance_data["value"],
        'spins_bought' => $spin_balance_data["bought"],
        'spin_balance' => $spin_balance_data["balance"],
        'redeemed' => $redeem_date ?? '',
      ];
    }
    else {
      $response = ['message' => 'invalid key'];
    }
    return new ResourceResponse($response);
  }

}
