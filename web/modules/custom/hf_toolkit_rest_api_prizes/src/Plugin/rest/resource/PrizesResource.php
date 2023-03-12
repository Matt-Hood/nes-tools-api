<?php

namespace Drupal\hf_toolkit_rest_api_prizes\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;

/**
 * Provides a HF Toolkit prize Resource.
 *
 * @RestResource(
 *   id = "hft_prize_resource",
 *   label = @Translation("HF Toolkit Access prize Resource"),
 *   uri_paths = {
 *     "canonical" = "/hf_toolkit_rest_api/prize_resource/{uid}"
 *   }
 * )
 */
class PrizesResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get($uid = ''): ResourceResponse {
    // @todo Look into seeing if I can load/match the access prize here with a public method from Drupal
    $all_prizes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => '1',
        'type' => 'prize',
      ]);

    $access_prize_data = [];
    if (!empty($all_prizes)) {
      /*
      Get the details of each access prize node and
      puts it in an array.
      We have to do this because we need to manipulate
      the array so that it will spit out exactly the XML we want
       */

      // @todo improve speed of search
      foreach ($all_prizes as $prize) {
        $title = '';
        if (!is_null($prize->getTitle())) {
          $title = $prize->getTitle() ?? '';
        }
        $account = User::load($uid);
        if ($title == 'Cash Prize') {
          $valid_prize = $prize->get('field_spin_prize')->getValue();
          $creation_date = $prize->getCreatedTime();
          $spin_balance = $account->get('field_spin_balance')->getValue();
          if (empty($spin_balance)) {
            $spin_balance = 0;
          }
          $spin_balance_value = intval($spin_balance[0]['value']);
          $user_updated_spin_balance = $spin_balance_value;
          $account->set('field_spin_balance', $user_updated_spin_balance);
          $account->save();
          $access_prize_data[] = [
            "name" => $title,
            "prize" => $valid_prize[0]['key'],
            "value" => rtrim(strip_tags($valid_prize[0]['value'])),
            "spin_balance" => $user_updated_spin_balance,
            "entity" => $prize,
          ];
          $redeem_date = date("Y-m-d\TH:i");
          // After access prize is found exit the loop and return.
        }
        if ($title == 'HF Sub') {
          $valid_prize = $prize->get('field_spin_prize')->getValue();
          $creation_date = $prize->getCreatedTime();
          $spin_balance = $account->get('field_spin_balance')->getValue();
          if (empty($spin_balance)) {
            $spin_balance = 0;
          }
          $spin_balance_value = intval($spin_balance[0]['value']);
          $user_updated_spin_balance = $spin_balance_value;
          $account->set('field_spin_balance', $user_updated_spin_balance);
          $account->save();
          $access_prize_data[] = [
            "name" => $title,
            "prize" => $valid_prize[0]['key'],
            "value" => rtrim(strip_tags($valid_prize[0]['value'])),
            "spin_balance" => $user_updated_spin_balance,
            "entity" => $prize,
          ];
          $redeem_date = date("Y-m-d\TH:i");
          // After access prize is found exit the loop and return.
        }
        if ($title == 'Ghost Sub') {
          $valid_prize = $prize->get('field_spin_prize')->getValue();
          $creation_date = $prize->getCreatedTime();
          $spin_balance = $account->get('field_spin_balance')->getValue();
          if (empty($spin_balance)) {
            $spin_balance = 0;
          }
          $spin_balance_value = intval($spin_balance[0]['value']);
          $user_updated_spin_balance = $spin_balance_value;
          $account->set('field_spin_balance', $user_updated_spin_balance);
          $account->save();
          $access_prize_data[] = [
            "name" => $title,
            "prize" => $valid_prize[0]['key'],
            "value" => rtrim(strip_tags($valid_prize[0]['value'])),
            "spin_balance" => $user_updated_spin_balance,
            "entity" => $prize,
          ];
          $redeem_date = date("Y-m-d\TH:i");
          // After access prize is found exit the loop and return.
        }
      }
    }

    if (!empty($access_prize_data)) {

      $filler = array_fill(0, 1000, array_fill(0, 10, 0));
      $diluated_prizes = array_merge($filler, $access_prize_data);

      shuffle($diluated_prizes);
      $prize_won = array_rand($diluated_prizes);
      if (!is_null($diluated_prizes[$prize_won]['name'])) {
        $diluated_prizes[$prize_won]["entity"]->setUnpublished();
        $diluated_prizes[$prize_won]["entity"]->save();
        $account = User::load($uid);
        $account->set('field_spin_balance', intval($account->get('field_spin_balance')->getValue()[0]['value'] - 1));
        $account->save();
        $response = [
          'message' => 'Congratulations you have won' . ' ' . $diluated_prizes[$prize_won]["name"] . " " . 'of' . " " . $diluated_prizes[$prize_won]["value"],
          'prize' => $diluated_prizes[$prize_won]["prize"],
          'redeemed' => $redeem_date ?? '',
          'spin balance' => intval($account->get('field_spin_balance')->getValue()[0]['value']),
        ];
      }
      else {
        $account = User::load($uid);
        $account->set('field_spin_balance', intval($account->get('field_spin_balance')->getValue()[0]['value']) - 1);
        $account->save();
        $response = [
          'message' => 'No prize won, if you are feeling lucky spin again!',
          'spin balance' => intval($account->get('field_spin_balance')->getValue()[0]['value']),
        ];
      }
    }
    else {
      $response = ['message' => 'invalid '];
    }
    return new ResourceResponse($response);
  }

}
