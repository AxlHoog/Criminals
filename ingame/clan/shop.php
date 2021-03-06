<?php
/*
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>. 
 */

require_once('../../init.php');

$error = array();
$form_error = '';

// Check if user is loggedin, if so no need to be here...
if (LOGGEDIN == FALSE) { header('Location: ' . ROOT_URL . 'index.php'); }

// Check if user has clan access to this page, if not no need to be here..
if ($userData['clan_level'] < 7) { header('Location: ' . ROOT_URL . 'ingame/clan/index.php'); }


if (isset($_GET['page']) AND !empty($_GET['page'])) {
    if ($_GET['page'] == 'sell') {
        $showPage = 'sell';
       // user has told what he want to sell, sell it!
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach ($_POST as $item => $value) {
                
                if ($item != 'submit') {
                    $item = str_replace('sell', '', $item);
                    
                    // check if has the power to sell the stuff he claims he wants to sell
                    if (!empty($value)) {
                        if (!ctype_digit($value)) {
                            $itemResult = $dbCon->query('SELECT item_name FROM items WHERE item_id = "' . addslashes($item) . '"')->fetch_assoc();
                            $error[] = 'De opgegeven waarden voor het verkopen van een ' . $itemResult['item_name'] . ' is niet numeriek!';
                        } else {
                            $itemResult = $dbCon->query('SELECT item_name, item_count FROM clan_items
                                                                            LEFT JOIN items ON clan_items.item_id = items.item_id WHERE clan_items.item_id = "' . addslashes($item) . '" AND clan_id = "'  . $userData['clan_id'] . '"')->fetch_assoc();
                            
                            if ($itemResult['item_count'] < $value) {
                                $error[] = 'Je hebt meer ingegeven dan je kan verkopen voor item ' . $itemResult['item_name'] . '!';
                            }
                        }
                    }
                    
                    if ($item == 27) {
                        $error[] = 'Eenmaal een huis gekocht kan je deze niet meer verkopen!';
                    }
                }            
            }
            
             if (count($error) > 0) {
                foreach ($error as $item) {
                    $form_error .= '- ' . $item . '<br />';
                }
                $tpl->assign('form_error', $form_error);
            } else {
                
                // clan may sell the stuff so let him do that
                foreach ($_POST as $item => $value) {
                
                    if ($item != 'submit') {
                        $item = str_replace('sell', '', $item);

                        if (!empty($value)) {
                            $sellItem = $dbCon->query('SELECT item_sell, item_count, item_attack_power, item_defence_power FROM items
                                                       LEFT JOIN clan_items ON items.item_id = clan_items.item_id WHERE clan_items.item_id = "' . addslashes($item) . '"
                                                       AND clan_items.clan_id = "' . $userData['clan_id'] . '"')->fetch_assoc();
                            
                            // Clan wants to sell it all, just delete the line...
                            if ($sellItem['item_count'] == $value) {
                                $dbCon->query('DELETE FROM clan_items WHERE clan_id = "' . $userData['clan_id'] . '" AND item_id = "' . addslashes($item) . '"');
                            } else {
                                $dbCon->query('UPDATE clan_items SET item_count = (item_count - "' . addslashes($value) . '") WHERE
                                               clan_id = "' . $userData['clan_id'] . '" AND item_id = "'. addslashes($item) . '"');
                            }

                            // And give the clan there money in cash but lower his attack / defence power
                            $dbCon->query('UPDATE clans SET cash = (cash + "' . (addslashes($value) * $sellItem['item_sell']) . '"),
                                                            attack_power = (attack_power - "' . ($sellItem['item_attack_power'] * addslashes($value)) . '"),
                                                            defence_power = (defence_power - "' . ($sellItem['item_defence_power'] * addslashes($value)) . '") WHERE clan_id = "' . $userData['clan_id'] . '"');

                            $tpl->assign('success', 'De shop is dankbaar voor de verkoop! Alles is succesvol verkocht!');
                        }
                    }
                }
            }
        }
        // show the sell page
            
        $userItems = array();
        $result = $dbCon->query('SELECT * FROM clan_items
                                          LEFT JOIN items ON clan_items.item_id = items.item_id WHERE clan_id = "' . $userData['clan_id'] . '" AND items.item_area BETWEEN 8 AND 11');
        while ($row = $result->fetch_assoc()) {
            $userItems[$row['item_id']]['id'] = $row['item_id'];
            $userItems[$row['item_id']]['name'] = $row['item_name'];
            $userItems[$row['item_id']]['count'] = $row['item_count'];
            $userItems[$row['item_id']]['attack_power'] = $row['item_attack_power'];
            $userItems[$row['item_id']]['defence_power'] = $row['item_defence_power'];
            $userItems[$row['item_id']]['total_attack_power'] = ($row['item_attack_power'] * $row['item_count']);
            $userItems[$row['item_id']]['total_defence_power'] = ($row['item_defence_power']  * $row['item_count']);
        }
            
        $tpl->assign('items', $userItems);
    }
    elseif ($_GET['page'] == 'buy') {
        $showPage = 'buy';
        
        $buy = array();
        $totalCosts = 0;
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach ($_POST as $item => $value) {
                if ($item != 'submit') {
                     
                    if (strpos($item, 'buy') !== false) {
                        $item = str_replace('buy', '', $item);

                        // check if has the power to sell the stuff he claims he wants to sell
                        if (!empty($value)) {
                            if (!ctype_digit($value)) {
                                $itemResult = $dbCon->query('SELECT item_name FROM items WHERE item_id = "' . addslashes($item) . '"')->fetch_assoc();
                                $error[] = 'De opgegeven waarden voor het kopen van een ' . $itemResult['item_name'] . ' is niet numeriek!';
                            } else {
                                $itemResult = $dbCon->query('SELECT * FROM items WHERE item_id = "' . addslashes($item) . '"')->fetch_assoc();

                                // Get total costs
                                $totalitemKosts = ($value * $itemResult['item_costs']);
                                $clanCash = $dbCon->query('SELECT cash FROM clans WHERE clan_id = "' . $userData['clan_id'] . '"')->fetch_assoc();
                                if ($totalitemKosts > $clanCash['cash']) {
                                    $error[] = 'Je kan niet zoveel kopen van een ' . $itemResult['item_name'] . '!';
                                }

                                // Check if the user wants to buy to much...
                                $totalCosts += $totalitemKosts;
                                if ($totalCosts > $clanCash['cash']) {
                                    $error[] = 'Je wilt te veel kopen zoveel cash heb je niet...';
                                }
                            }
                        }
                    }
                }
            }
           
            if (count($error) > 0) {
                foreach ($error as $item) {
                    $form_error .= '- ' . $item . '<br />';
                }
                $tpl->assign('form_error', $form_error);
            } else {
                 
                 // User may buy it
                 foreach ($_POST as $item => $value) {
                    if ($item != 'submit') {

                        if (strpos($item, 'buy') !== false) {
                            $item = str_replace('buy', '', $item);

                            if ($value > 0) {
                                // Check if there is a row for the clan and item if so update, if not insert
                                $checkResult = $dbCon->query('SELECT clan_id FROM clan_items WHERE
                                                              clan_id = "' . $userData['clan_id'] . '" AND item_id = "' . addslashes($item) . '"')->num_rows;

                                if ($checkResult > 0) {
                                    $dbCon->query('UPDATE clan_items SET item_count = (item_count + "' . addslashes($value) . '") WHERE
                                                   clan_id = "' . $userData['clan_id'] . '" AND item_id = "' . addslashes($item) . '"')  or die(mysqli_error($dbCon));
                                } else {
                                    $dbCon->query('INSERT INTO clan_items (clan_id, item_id, item_count) VALUES
                                            ("'. $userData['clan_id'] . '", "' . addslashes($item) . '", "' . addslashes($value) . '")') or die(mysqli_error($dbCon));
                                }

                                // And now get the money from the clan!
                                $costResult = $dbCon->query('SELECT item_costs, item_attack_power, item_defence_power FROM items WHERE item_id = "' . addslashes($item) . '"')->fetch_assoc();
                                $costs = ($value * $costResult['item_costs']);
                                
                                if (!isset($costResult['attack_power'])) { $costResult['attack_power'] = 0; }
                                if (!isset($costResult['defence_power'])) { $costResult['defence_power'] = 0; }
                                
                                $dbCon->query('UPDATE clans SET cash = (cash - "' . $costs . '"), 
                                                                attack_power = (attack_power + "' . ($costResult['attack_power'] * addslashes($value)) . '"),
                                                                defence_power = (defence_power + "' . ($costResult['defence_power'] * addslashes($value)) . '") WHERE clan_id = "' . $userData['clan_id'] .'"');
                                
                                $tpl->assign('success', 'De transactie is succesvol afgerond!');
                            }
                        }
                    }
                }
            }
        }

        $itemArray = array();
        $itemsResult = $dbCon->query('SELECT * FROM items WHERE item_area = 8 OR item_area = "' . ($userData['type'] + 8) . '"');
        $clanCash = $dbCon->query('SELECT cash FROM clans WHERE clan_id = "' . $userData['clan_id'] . '"')->fetch_assoc();
        
        while ($items = $itemsResult->fetch_assoc()) {
            $itemArray[$items['item_id']]['id'] = $items['item_id'];
            $itemArray[$items['item_id']]['name'] = $items['item_name'];
            $itemArray[$items['item_id']]['attack_power'] = $items['item_attack_power'];
            $itemArray[$items['item_id']]['defence_power'] = $items['item_defence_power'];
            $itemArray[$items['item_id']]['costs'] = $items['item_costs'];
            $itemArray[$items['item_id']]['max_buy'] = floor(($clanCash['cash'] / $items['item_costs']));
        }
        $tpl->assign('items', $itemArray);
    }
} else {
    header('Location: shop.php?page=buy');
}

$tpl->display('clan/' . $showPage . '.tpl');