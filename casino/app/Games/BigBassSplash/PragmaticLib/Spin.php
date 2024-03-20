<?php

namespace VanguardLTE\Games\BigBassSplash\PragmaticLib;

use VanguardLTE\Services\Api\Api;

class Spin
{
    public static function spinResult($user, $game, $bet, $lines, $log, $gameSettings, $index, $counter, $callbackUrl, $pur, $bank, $shop, $jpgs){

      //  if ($user->balance < $bet * $lines) return false;
        
        $gameSettings = $gameSettings->all;
        $newSpinCnt = 0;
        $currentLog = $log->getLog();
        // $lines = $doubleChance == 0 ? $lines : $lines * 1.25;
        if ($currentLog && array_key_exists('fs', $currentLog)){
            $changeBalance = 0;
        }else{
            $changeBalance = ($bet * $lines * -1);
            if ($pur === '0') $changeBalance *= 100;
        }
        if ($user->balance < -1 * $changeBalance) return false;
        NewSpin:
        //build a playing field
        $reelSet = rand(1, 10); // reel set is random num between 1 and 10
        $pur1 = $pur;
        if($pur1 == '0')
            $reelSet = 0;   // reelset is 0 when purchasing
        if ($currentLog && array_key_exists('fs', $currentLog)){
            if(array_key_exists('trail', $currentLog)){
                $reelSet = 11;
                $trails = $currentLog['trail'];
                $trails = explode(';', $trails);
                foreach($trails as $index => $trail){
                    $trails[$index] = explode('~', $trail);
                }
                // var_dump($trails);
                if($trails[3][1] == '1') $reelSet += 1;  // if mf == 1
                if($trails[0][1] == '1') $reelSet += 2;  // if mm == 1
            }
            else $reelSet = $currentLog['reel_set'];
        }
        $slotArea = SlotArea::getSlotArea($gameSettings,$reelSet,$currentLog);
        var_dump('1');
        
        if ($pur1 === '0') 
        $slotArea['ScatterCount'] = BuyFreeSpins::getFreeSpin($slotArea['SlotArea'], $gameSettings, 3, 3); // buy freespins
        else {
            if($currentLog && array_key_exists('fs', $currentLog) && $currentLog['fs'] == $currentLog['fsmax'])
                $slotArea['ScatterCount'] = BuyFreeSpins::getFreeSpin($slotArea['SlotArea'], $gameSettings, 1, 0);
            else if($currentLog && array_key_exists('fs', $currentLog))
                $slotArea['ScatterCount'] = BuyFreeSpins::getFreeSpin($slotArea['SlotArea'], $gameSettings, 0, 0);
            else {
                  $slotArea['ScatterCount'] = BuyFreeSpins::getFreeSpin($slotArea['SlotArea'], $gameSettings, 0, 3);
                }
        }
        if($currentLog && array_key_exists('fs', $currentLog) && $currentLog['fs'] != 1)
            BuyFreeSpins::getWild($slotArea['SlotArea'], $gameSettings, 0, 1, $currentLog);
        var_dump('2');
        // if scatter count is greater than settings_needfs make pur = 0
        if($slotArea['ScatterCount'] >= $gameSettings['settings_needfs'])
        if(!$currentLog || $currentLog && !array_key_exists('fs', $currentLog))
            $pur1 = '0';
        else {
            var_dump('2_1_scatterCount='.$slotArea['ScatterCount'].'_settings-needfs='.$gameSettings['settings_needfs']);
            $pur1 = '1';
        }
        // Set MO values
        SlotArea::getMO($gameSettings, $slotArea, $currentLog);
        //check win (return array with win amount and symbol positions
        $winChecker = new WinChecker($gameSettings);
        $win = $winChecker->getWin($pur1, $currentLog, $bet, $slotArea, $gameSettings);
        var_dump('3');
        
        //put everything in a convenient array
        $logAndServer = LogAndServer::getResult($slotArea, $index, $counter, $bet, $lines, $reelSet,
        $win, $pur1, $currentLog, $user, $changeBalance, $gameSettings, $game, $bank);
        var_dump('6');
        // check if you can win
        
        $fswin = array_key_exists('fswin', $win) ? $win['fswin'] : 0;
        if ($win['TotalWin'] + $fswin > 0)
        $win_permission = WinPermission::winCheck($fswin,$pur1,$bank,$logAndServer['Log'],$win['TotalWin'], $currentLog, $changeBalance, $shop);
        else $win_permission = true;
        var_dump('7');
        if (!$win_permission) {
            $newSpinCnt ++;
            goto NewSpin;
        }

        // check rtp when you spin
        $checkRtpSlots = new CheckRtp($gameSettings['rtp_slots'], $game);
        if($pur1 != '0' && $currentLog && !array_key_exists('fs', $currentLog) && !$checkRtpSlots->checkRtp($bet * $lines, $win['TotalWin']) && $newSpinCnt < 4 && $bank->slots > $bet * $lines){
            $newSpinCnt ++;
            goto NewSpin;
        }
        // check rtp when you're on free spin
        if($currentLog && array_key_exists('fs', $currentLog)){
            $limit = $bet * $lines * 100 / 10;
            if(array_key_exists('accv', $currentLog)){
                $accv = explode('~', $currentLog['accv']);
                $accv = $accv[2] + 1;
                $limit *= $accv;
            }
            $checkRtpBonus = new CheckRtp($gameSettings['rtp_bonus'], $game);
            if($currentLog 
            && array_key_exists('fs', $currentLog) 
            && !$checkRtpBonus->checkRtp($limit, $win['TotalWin'] + $fswin)
            && $bank->bonus + $bank->slots > $limit
            && $newSpinCnt < 4){
                $newSpinCnt ++;
                goto NewSpin;
            }
        }
        
        // allocate money to the bank and write it down in statistics
        // $freeSpins = 0;
        SwitchMoney::set($pur1, $changeBalance, $shop, $bank, $jpgs, $user, $game, $callbackUrl, $win['TotalWin'], $slotArea, $fswin, $logAndServer['Log'], $win_permission);
        var_dump('8');
        //write a log
        Log::setLog($logAndServer['Log'], $game->id, $user->id, $user->shop_id);
        var_dump('9');

        //give to the server
        $response = '&'.(implode('&', $logAndServer['Server']));
        var_dump('10');
        return $response;
    }

}
