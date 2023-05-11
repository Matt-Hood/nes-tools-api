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
    // Set default timezone to UTC.
    date_default_timezone_set('UTC');

    // @todo Look into seeing if I can load/match the access key here with a public method from Drupal
    $all_access_keys = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => '1',
        'type' => 'access_keys',
      ]);
    $redeemed_access_keys = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => '0',
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

      // Active Keys Loop.
      foreach ($all_access_keys as $key) {
        $account = User::load($uid);
        $valid_access_key = '';
        $user_hwid = $hwid;
        $hwid_set = FALSE;
        if (!is_null($key->get('field_subscription_type')->referencedEntities()[0])) {
          $valid_access_key = $key->get('field_subscription_type')->referencedEntities()[0]->getName() ?? '';
        }
        if (!is_null($account->get('field_hwid')->value)) {
          $user_hwid = $account->get('field_hwid')->value;
          $hwid_set = TRUE;
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
            }

            $redeem_date = date_create(date("y-m-d h:i:s"));
            date_add($redeem_date, date_interval_create_from_date_string("${expiration_date_number} days"));

            $redeem_date = date_format($redeem_date, "Y-m-d\TH:i:s");

            $account->set('field_key_expiration', $redeem_date);
            $account->save();

            if ($user_hwid == $hwid) {
              if (!$hwid_set) {
                $account->set('field_hwid', $hwid);
                $account->save();
              }

              $redeemed_keys = $account->get('field_redeemed_keys')->getValue();
              $current_date = date("y-m-d h:i:s");
              $redeemed_keys[] = [
                'key' => $valid_key[0]['key'],
                'value' => $valid_key[0]['value'],
                'description' => 'redeemed on: ' . $current_date,
              ];
              $account->set('field_redeemed_keys', $redeemed_keys);
              $account->save();

              $expiration_date = $account->get('field_key_expiration')->getValue();
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
            if ($user_hwid != $hwid) {
              $access_key_data = [
                "key" => 'HWID Access Denied',
                "value" => 'HWID Access Denied',
                "expiration" => 'HWID Access Denied',
                "hwid_access" => 'HWID Access Denied',
              ];

            }
          }
        }
      }

      // Redeemed Keys Loop.
      foreach ($redeemed_access_keys as $key) {
        $account = User::load($uid);
        $valid_access_key = '';
        $user_hwid = $hwid;
        $hwid_set = FALSE;
        if (!is_null($key->get('field_subscription_type')->referencedEntities()[0])) {
          $valid_access_key = $key->get('field_subscription_type')->referencedEntities()[0]->getName() ?? '';
        }
        if (!is_null($account->get('field_hwid')->value)) {
          $user_hwid = $account->get('field_hwid')->value;
          $hwid_set = TRUE;
        }

        if ($valid_access_key == 'HF Toolkit Subscription Time') {
          $valid_key = $key->get('field_access_key_')->getValue();

          if ($valid_key[0]['value'] == $access_key) {

            $expire_date = $account->get('field_key_expiration')->getValue();
            $expire_date_formatted = $expire_date[0]["value"];
            $current_date = date("Y-m-d\TH:i:s");
            if ($current_date > $expire_date_formatted) {

              if ($user_hwid == $hwid) {
                if (!$hwid_set) {
                  $account->set('field_hwid', $hwid);
                  $account->save();
                }
                $access_key_data = [
                  "key" => $valid_key[0]['key'],
                  "value" => $valid_key[0]['value'],
                  "status" => 'expired key',
                  "expiration" => 'expired',
                ];

                // After access key is found exit the loop and return.
                break;
              }
            }
            else {
              if ($user_hwid == $hwid) {
                if (!$hwid_set) {
                  $account->set('field_hwid', $hwid);
                  $account->save();
                }

                $access_key_data = [
                  "key" => $valid_key[0]['key'],
                  "value" => $valid_key[0]['value'],
                  "status" => 'active key',
                  "expiration" => $expire_date,
                ];

                // After access key is found exit the loop and return.
                break;
              }
            }
          }
        }
      }
    }

    if (!empty($access_key_data)) {
      if ($access_key_data["expiration"] == 'expired') {
        $response = [
          'message' => 'Successfully redeemed accesss key' . ' ' . $access_key_data["value"],
          'subscription' => $access_key_data["key"],
          'redeemed' => $redeem_date ?? '',
          'hwid access' => 'Access Granted',
          'status' => $access_key_data["status"] ?? 'First Time Use',
        ];
      }
      if ($access_key_data["expiration"] == 'active') {
        $response = [
          'message' => 'Successfully redeemed accesss key' . ' ' . $access_key_data["value"],
          'subscription' => $access_key_data["key"],
          'redeemed' => $redeem_date ?? '',
          'hwid access' => 'Access Granted',
          'status' => $access_key_data["status"] ?? 'First Time Use',
        ];
      }
      else {
        $response = [
          'message' => 'Successfully redeemed accesss key' . ' ' . $access_key_data["value"],
          'subscription' => $access_key_data["key"],
          'redeemed' => $redeem_date ?? '',
          'hwid access' => 'Access Granted',
          'key expiration' => $access_key_data["expiration"],
          'status' => $access_key_data["status"] ?? 'First Time Use',
        ];
      }

    }
    else {
      $response = ['message' => 'invalid key'];
    }
    return new ResourceResponse($response);
  }

}
