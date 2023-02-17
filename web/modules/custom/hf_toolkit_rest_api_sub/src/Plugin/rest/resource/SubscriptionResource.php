<?php

namespace Drupal\hf_toolkit_rest_api_sub\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;

/**
 * Provides a HF Toolkit Key Resource.
 *
 * @RestResource(
 *   id = "toolkit_sub_resource",
 *   label = @Translation("HF Toolkit Access Key Resource"),
 *   uri_paths = {
 *     "canonical" = "/hf_toolkit_rest_api/toolkit_sub_resource/{access_key}"
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

    $access_key_uuid_data = explode("--", $access_key);
    $uid = $access_key_uuid_data[0];
    $hwid = $access_key_uuid_data[1];
    $access_key = $access_key_uuid_data[2];

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
        $account = User::load($uid);
        $valid_access_key = '';
        $user_hwid = '';
        if (!is_null($key->get('field_subscription_type')->referencedEntities()[0])) {
          $valid_access_key = $key->get('field_subscription_type')->referencedEntities()[0]->getName() ?? '';
        }
        if (!empty($account->get('field_hwid')->getValue())) {
          $user_hwid = $account->get('field_hwid')->getValue();
        }
        else {
          $user_hwid = $hwid;
        }
        if ($valid_access_key == 'HF Toolkit Subscription Time') {
          $valid_key = $key->get('field_access_key_')->getValue();
          $creation_date = $key->getCreatedTime();

          if ($valid_key[0]['value'] == $access_key) {
            $expiration_date_number = '0';
            switch ($valid_key[0]['key']) {
              case 'Month Key':
                $expiration_date_number = '30';
                break;

              case 'Week Key':
                $expiration_date_number = '7';
                break;

              case 'Day Key':
                $expiration_date_number = '1';
                break;

              default:
                $expiration_date_number = '1';
            }

            $redeem_date = date_create(date("y-m-d h:i:s"));
            date_add($redeem_date, date_interval_create_from_date_string("30 days"));

            $redeem_date = date_format($redeem_date, "Y-m-d\TH:i:s");

            $account->set('field_key_expiration', $redeem_date);
            $account->save();
            if ($user_hwid == $hwid) {
              $redeemed_keys = $account->get('field_redeemed_keys')->getValue();
              $expiration_date = $account->get('field_key_expiration');
              $access_key_data = [
                "key" => $valid_key[0]['key'],
                "value" => $valid_key[0]['value'],
                "expiration" => $expiration_date,
              ];

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
    }

    if (!empty($access_key_data)) {
      $response = [
        'message' => 'Successfully redeemed accesss key' . ' ' . $access_key_data["value"],
        'subscription' => $access_key_data["key"],
        'redeemed' => $redeem_date ?? '',
        'hwid access' => 'Access Granted',
        'key expiration' => $access_key_data["expiration"],
      ];
    }
    else {
      $response = ['message' => 'invalid key'];
    }
    return new ResourceResponse($response);
  }

}
