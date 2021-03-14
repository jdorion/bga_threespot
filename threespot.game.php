<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * ThreeSpot implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * threespot.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class ThreeSpot extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        parent::__construct();
        self::initGameStateLabels( array( 
                         "handColor" => 10, 
                         "trickColor" => 11,
                         "teamA_hand_points" => 12,
                         "teamB_hand_points" => 13,
                         "teamA1" => 14,
                         "teamA2" => 15,
                         "dealerPlayerID" => 16,
                         "bestBidder" => 17,
                         "bestBid" => 18,
                         "numBids" => 19
                          ) );

        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );

	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "threespot";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    


        // Retrieve inital player order ([0=>playerId1, 1=>playerId2, ...])
        $playerInitialOrder = [];
        foreach ($players as $playerId => $player) {
            $playerInitialOrder[$player['player_table_order']] = $playerId;
        }
        ksort($playerInitialOrder);
        $playerInitialOrder = array_flip(array_values($playerInitialOrder));

        // Player order based on 'playerTeams' option
        $playerOrder = [0, 1, 2, 3];

        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, player_no) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = 
                "('" .
                $player_id .
                "','$color','" .
                $player['player_canal'] .
                "','" .
                addslashes( $player['player_name'] ) .
                "','" .
                addslashes( $player['player_avatar'] ) .
                "','" .
                $playerOrder[$playerInitialOrder[$player_id]] .
                "')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/
        // Init global values with their initial values

        // Create cards
        $cards = array ();
        foreach ( $this->colors as $color_id => $color ) {
            // spade, heart, diamond, club
            for ($value = 7; $value <= 14; $value ++) {
                // special logic for the 5 and the 3
                $card_value = $value;
                if ($card_value == 7 && $color_id == 1) {
                    $card_value = 3;
                } else if ($card_value == 7 && $color_id == 2) {
                    $card_value = 5;
                }
                //  7, 3, 4, ... K, A
                $cards [] = array ('type' => $color_id,'type_arg' => $card_value,'nbr' => 1 );
            }
        }
        
        $this->cards->createCards( $cards, 'deck' );


        // create bids
        // TODO: make min_bid configurable from game options
        $min_bid = 7;
        $sql = "INSERT INTO bid (bid_value, no_trump, label) VALUES";
        $values = array();
        // pass bid
        $values[] = "('0', '0', 'Pass')";
        for ($x = $min_bid; $x <= 10; $x++) {
            // add regular bid
            $values[] = "('$x', '0', '$x')";
            // add no-trump bid
            $values[] = "('$x', '1', '$x - no trump')";
        }
        // three spot bids (auto-win if successful)
        $values[] = "('12', '0', 'Three spot')";
        $values[] = "('12', '1', 'Three spot - no trump')";

        $sql .= implode ( $values, ',' );
        self::DbQuery( $sql );


        // Init global values with their initial values

        // Set trump of the hand to zero (= no trump yet, will eventually be set in the bidding process)
        self::setGameStateInitialValue( 'handColor', 0 );        
        // Set current trick color to zero (= no trick color)
        self::setGameStateInitialValue( 'trickColor', 0 );
        // set the total hand points for each team to 0;
        self::setGameStateInitialValue('teamA_hand_points', 0);
        self::setGameStateInitialValue('teamB_hand_points', 0);
        // set bid globals
        self::setGameStateInitialValue('bestBidder', 0);
        self::setGameStateInitialValue('bestBid', 0);
        self::setGameStateInitialValue('numBids', 0);

        // initialize team members
        $orderedPlayers = self::loadPlayersBasicInfos();
        $keys = array_keys($orderedPlayers);
        self::setGameStateInitialValue('teamA1', $keys[0]);
        self::setGameStateInitialValue('teamA2', $keys[2]);
        // set the dealer to player 1
        // TO DO: randomize this
        self::setGameStateInitialValue('dealerPlayerID', $keys[0]);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // possible stats (table): bids succeeded, 
        // possible stats (player): total # of tricks won, bids succeeded, taken 5, taken 3, 

        // TODO: setup the initial game situation here
        // Activate first player (which is in general a good idea :) )
        
        // in Three Spot, the first player is the dealer, but the first bidder is the second player so we need to call this twice
        //$this->activeNextPlayer();
        //$this->activeNextPlayer();

        // after dealer is randomized, this has to be 'player after dealer'
        // I think next state might activate the next player anyway, so maybe simply setting it do be dealer id works??
        $this->gamestate->changeActivePlayer( $keys[0] );
        $this->gamestate->nextState("");                
        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $players = self::getCollectionFromDb( $sql );
        
        $newPlayers = array();
        // adding team info and if they're the current dealer
        foreach( $players as $player_id => $player )
        {
            if (self::isTeamA($player_id)) {
                $player['team'] = "Team A";
            } else {
                $player['team'] = "Team B";
            }

            if ($player_id == self::getGameStateValue('dealerPlayerID')) {
                $player['dealer'] = "Dealer";
            } else {
                $player['dealer'] = "";
            }

            $newPlayers[$player_id] = $player;
        }
        $result['players'] = $newPlayers;
  
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation( 'hand', $current_player_id );
                
        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );

        // current trump
        $currentTrumpColor = self::getGameStateValue('handColor');
        $currentBid = self::getGameStateValue('bestBid');
        $bestBidder = self::getGameStateValue('bestBidder');

        if (self::isTeamA($bestBidder)) {
            $result['biddingTeam'] = "Team A";
        } else {
            $result['biddingTeam'] = "Team B";
        }

        if ($currentBid == 0) {
            $result['bet'] = "not set";
        } else {
            $bid = self::getBid($currentBid);
            $result['bet'] = $bid['bid_value'];
        }

        if ($currentTrumpColor == 0) {
            if ($currentBid == 0) {
                $result['trump'] = "not set";
            } else {
                $result['trump'] = "No trump";
            }
        } else {
            $result['trump'] = $this->colors [$currentTrumpColor] ['name'];
        }
        

        
        // how many tricks team A&B have in the current hand
        $result['teama'] = self::getGameStateValue('teamA_hand_points');
        $result['teamb'] = self::getGameStateValue('teamB_hand_points');

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    // get score
    function dbGetScore($player_id) {
        return $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
    }
    // set score
    function dbSetScore($player_id, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
    }

    // increment score (can be negative too)
    function dbIncScore($player_id, $inc) {
        $count = $this->dbGetScore($player_id);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScore($player_id, $count);
        }
        return $count;
    }

    function getPlayerIds() {
        $players = self::loadPlayersBasicInfos();
        $result = array();
        // adding team info and if they're the current dealer
        foreach( $players as $player_id => $player )
        {
            $result[] = $player_id;
        }

        self::dump('player ids', $result);
        return $result;
    }

    // figures out which cards can be played
    function getValidCards($player_id) {
        $hand = $this->cards->getCardsInLocation( 'hand', $player_id );
        $currentTrickColor = self::getGameStateValue('trickColor');
    
        // If not the first card of the trick
        if($currentTrickColor != 0){
          // Keep only the cards with matching color (if at least one such card)
          $filteredHand = array_values(array_filter($hand, function($card) use ($currentTrickColor){ return $card['type'] == $currentTrickColor; }));
        }

        // if there are no matching card in the suit or this is the first card in the trick, all cards are valid
        if(empty($filteredHand)) {
            // need to run the hand through array_values to fix the indexes
            $filteredHand = array_values($hand);
        }
        $hand = $filteredHand;
    
        $cards = array_map(function($card){ return $card['id'];}, $hand);
        return $cards;
    }

    function isThreeSpades($card) {
        return $card['type'] == 1 && $card['type_arg'] == 3;
    }

    function isFiveHearts($card) {
        return $card['type'] == 2 && $card['type_arg'] == 5;
    }

    function isTeamA($player) {
        $teamA1 = self::getGameStateValue('teamA1');
        $teamA2 = self::getGameStateValue('teamA2');
        return ($player == $teamA1) || ($player == $teamA2);
    }

    function playerIsOnBidWinningTeam($player_id) {

        $bestBidder = self::getGameStateValue('bestBidder');
        $playerIsTeamA = self::isTeamA($player_id);
        $bidderIsTeamA = self::isTeamA($bestBidder);

        return $playerIsTeamA == $bidderIsTeamA;
    }

    function getValidBids($player) {

        $currentBid = self::getCurrentBid();
        $currentBidId = (is_null($currentBid)) ? 0 : $currentBid['bid_id'];
        $currentBidValue = (is_null($currentBid)) ? 0 : $currentBid['bid_value'];
        $allBids = self::getAllBids();
        $isDealer = $player == self::getGameStateValue('dealerPlayerID');
        $result = array();

        self::dump("getValidBids player", $player);
        self::dump('getValidBids isDealer', $isDealer);
        self::dump('current bid', $currentBid);
        self::dump('currentBidId', $currentBidId);
        self::dump('currentBidValue', $currentBidValue);

        // this is dependent on bid ids being set in ascending order
        foreach ($allBids as $bid_id => $bid ) {

            $bid_id = $bid['bid_id'];
            $value = $bid['bid_value'];
            $no_trump = $bid['no_trump'];

            // pass bid
            if ($bid_id == 1) {

                // dealer can pass only if there's an existing bid
                if ($isDealer) {
                    if ($currentBidValue != 0) {
                        $result[$bid_id] = $bid;
                    }
                } else {
                    $result[$bid_id] = $bid;
                }
            } else {
                
                if ($isDealer && $bid_id >= $currentBidId) {
                    // dealer can take current bid or any higher bid
                    $result[$bid_id] = $bid;
                } else if (!$isDealer && $bid_id > $currentBidId){
                    // players other than the dealer can only bid higher
                    $result[$bid_id] = $bid;
                }
            }
        }

        return $result;
    }

    function getAllBids() {
        //self::getCollectionFromDB( "SELECT player_id id, player_name name, player_score score FROM player" );
        //Result:
        //array(
        // 1234 => array( 'id'=>1234, 'name'=>'myuser0', 'score'=>1 ),
        // 1235 => array( 'id'=>1235, 'name'=>'myuser1', 'score'=>0 )
        //)

        $result = self::getCollectionFromDB("SELECT bid_id bid_id, bid_value bid_value, no_trump no_trump, label label FROM bid");
        return $result;
    }

    function getBid($bid_id) {
        //self::getObjectFromDB( "SELECT player_id id, player_name name, player_score score FROM player WHERE player_id='$player_id'" );
        //Result:
        //array(
        //    'id'=>1234, 'name'=>'myuser0', 'score'=>1 
        //)

        $result = self::getObjectFromDB( "SELECT bid_id bid_id, bid_value bid_value, no_trump no_trump, label label FROM bid WHERE bid_id = $bid_id " );
        return $result;        
    }

    function getCurrentBid() {
        return self::getBid(self::getGameStateValue('bestBid'));
    }

    function isPassingBid($bid_id) {
        $bid = self::getBid($bid_id);
        return $bid['bid_value'] == 0;
    }

    function calculateBidResult($tricksWon, $bestBid) {

        // this cast doesn't work when it's a negative value
        $tricksWon = intval($tricksWon);
        $bid = self::getBid($bestBid);
        $bidValue = (int)$bid['bid_value'];

        self::dump('tricks won: ', $tricksWon);
        self::dump('bid value: ', $bidValue);
        $result = 0;
        if ($tricksWon >= $bidValue) {
            // if they got an amount of tricks greater than or equal to what was bet, 
            // they should get that amount of points!
            $result = $tricksWon;
        } else {
            // if they didn't make their bid, they get minus bid value points
            $result = $bidValue * -1;
        }

        // bidding no-trump means double the result
        if ($bid['no_trump'] == 1) {
            $result = $result * 2;       
        }

        self::dump('result: ', $result);
        return $result;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in threespot.action.php)
    */
    function makeBid($bid_id) {
        self::checkAction("makeBid");
        $player_id = self::getActivePlayerId();
        $bid = self::getBid($bid_id);
        $validBids = self::getValidBids($player_id);
        if (in_array($bid, $validBids)) {

            // increment number of bids even if it's a pass (it's how we have to check if everyone has bid)
            $numBids = self::getGameStateValue('numBids');
            self::SetGameStateValue('numBids', $numBids + 1);
            
            // if it's a pass, no need to set anything
            if (self::isPassingBid($bid_id)) {
                // notify
                self::notifyAllPlayers(
                    'bidMade', 
                    clienttranslate('${player_name} passes.'), 
                    array (
                        'player_name' => self::getActivePlayerName(),
                    )
                );
            } else {
                // if this wasn't a pass, save bidder and bid id 
                // (the only valid bids apart from pass are higher than what was already bid)
                self::setGameStateValue('bestBidder', $player_id);
                self::setGameStateValue('bestBid', $bid_id);
                
                self::dump('bid made. bestBidder', $player_id);
                self::dump('bid made. bid id', $bid_id);
                // And notify
                self::notifyAllPlayers(
                    'bidMade', 
                    clienttranslate('${player_name} bids ${label}'), 
                    array (
                        'player_name' => self::getActivePlayerName(),
                        'label' => $bid['label'],
                    )
                );
            }

            // Next player
            $this->gamestate->nextState('makeBid');
        } else {
            self::dump( 'bid id', $bid_id );
            throw new feException( self::_("You are not allowed to make that bid!"), true );
        }
    }

    function setTrump($trump_id) {
        self::checkAction("setTrump");
        $player_id = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();
        $bid = self::getBid(self::getGameStateValue( 'bestBid' ));

        if ((1 <= $trump_id) && ($trump_id <= 4)) {
            // set the trump global variable
            self::setGameStateInitialValue('handColor', $trump_id);
            
            //notify all players to update the handinfo div with the new hand info
            self::notifyAllPlayers( 'trumpSet', clienttranslate('${player_name} has won the bet with ${bet}, trump is ${trump}'), array(
                'player_name' => $players[ $player_id ]['player_name'],
                'biddingTeam' => (self::isTeamA($player_id)) ? "Team A" : "Team B",
                'bet' => $bid['bid_value'],
                'trump' => $this->colors [$trump_id] ['name']
            ) );  
            
            // Next player
            $this->gamestate->nextState('newTrick');
        } else {
            throw new feException( self::_("Invalid trump set! " + $trump_id), true );
        }
    }

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();

        $validCards = self::getValidCards($player_id);
        if (in_array($card_id, $validCards)) {
            $this->cards->moveCard($card_id, 'cardsontable', $player_id);
            $currentCard = $this->cards->getCard($card_id);

            // set the current trick color if it it hasn't yet
            $currentTrickColor = self::getGameStateValue( 'trickColor' ) ;
            if( $currentTrickColor == 0 ) {
                self::setGameStateValue( 'trickColor', $currentCard['type'] );
            }
            
            // check if card played is trump (for notification)
            $currentTrumpColor = self::getGameStateValue( 'handColor' );
            $trump = "";
            if ($currentCard['type'] == $currentTrumpColor) {
                $trump = "(trump)";
            }

            // And notify
            self::notifyAllPlayers(
                'playCard', 
                clienttranslate('${player_name} plays ${value_displayed}${color_displayed} ${trump}'), 
                array (
                'i18n' => array (
                    'color_displayed',
                    'value_displayed' 
                ),
                'player_name' => self::getActivePlayerName(),
                'value_displayed' => $this->values_label [$currentCard ['type_arg']],
                'color_displayed' => $this->colors [$currentCard ['type']] ['name'],
                'trump' => $trump,
                
                'player_id' => $player_id,
                'color' => $currentCard ['type'],
                'value' => $currentCard ['type_arg'],
                'card_id' => $card_id,
                )
            );

            // Next player
            $this->gamestate->nextState('playCard');
        } else {
            throw new feException( self::_("You can't play that card - you must follow suit if possible."), true );
        }
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */
  /*
   * Return the list of cards that can be played during the trick
   */
  function argPlayerTurn()
  {
    $player_id = self::getActivePlayerId();

    $result = array();
    $result['cards'] = self::getValidCards($player_id);

    //self::dump( 'cards', $result );
    return $result;
  }

  function argBiddingTurn() {
    $player_id = self::getActivePlayerId();
    
    $players = self::loadPlayersBasicInfos();
    $firstBidder = $players[self::getActivePlayerId()];
    $dealerPlayer = $players[self::getGameStateValue('dealerPlayerID')];
    $teamA1 = $players[self::getGameStateValue('teamA1')];
    $teamA2 = $players[self::getGameStateValue('teamA2')];
    self::debug('New Hand');
    self::dump('current bidder: ', $firstBidder['player_name']);
    self::dump('dealer: ', $dealerPlayer['player_name']);
    self::dump('teamA1: ', $teamA1['player_name']);
    self::dump('teamA2: ', $teamA2['player_name']);
    self::dump('numBids', self::getGameStateValue('numBids'));

    // check existing bid and only return 'Pass' / valid bids / take current bid (if dealer)
    $result = array();
    $result['bids'] = self::getValidBids($player_id);

    //self::dump( 'bids', $result );
    return $result;
  }

  function argSettingTrump() {
    $result = array();

    // pass list of suits
    $suits = array(
        1 => '♠ - spades',
        2 => '♥ - hearts',
        3 => '♣ - clubs',
        4 => '♦ - diamonds'
    );

    $result['suits'] = $suits;
    return $result;
  }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewHand() {

        // reset hand point total and trump
        self::setGameStateInitialValue('teamA_hand_points', 0);
        self::setGameStateInitialValue('teamB_hand_points', 0);
        self::setGameStateInitialValue('handColor', 0);

        // reset the bid globals
        self::setGameStateInitialValue('bestBidder', 0);
        self::setGameStateInitialValue('bestBid', 0);
        self::setGameStateInitialValue('numBids', 0);



        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');

        // Deal 8 cards to each players
        // Create deck, shuffle it and give 8 initial cards
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            $cards = $this->cards->pickCards(8, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array ('cards' => $cards ));
        }

        // update the dealer flag on the player panel
        self::notifyAllPlayers( 'newDealer', '', array(
            'id' => self::getGameStateValue('dealerPlayerID'),
            'playerIds' => self::getPlayerIds(),
            ) ); 

        // the correct player was set in stEndHand(), so switch state to ask them to bid
        $this->gamestate->nextState("");
    }

    function stNextBid() {

        $numBids = self::getGameStateValue('numBids');

        // if dealer has bid, then get bidwinner to set trump
        //if ($player_id == self::getGameStateValue('dealerPlayerID')) {
        if ($numBids == 4) {

            $bestBidder = self::getGameStateValue('bestBidder');
            $bid = self::getBid(self::getGameStateValue( 'bestBid' ));

            // if this was a trump bid, ask bid winner to set trump
            if ($bid['no_trump'] == 0) {

                self::debug('setting trump');
                // ask bid winner to set trump
                $this->gamestate->changeActivePlayer($bestBidder);
                self::giveExtraTime($bestBidder);
                $this->gamestate->nextState('settingTrump');
            } else {
            // otherwise, notify all players about the winning bet and no trump, go directly to new trick

                $players = self::loadPlayersBasicInfos();        
                $player_name = $players[ $bestBidder ]['player_name'];
                $bid_value = $bid['bid_value'];

                self::dump('player name', $player_name);
                self::dump('bid value', $bid_value);

                //notify all players to update the handinfo div with the new hand info
                self::notifyAllPlayers( 'trumpSet', clienttranslate('${player_name} has won the bet with ${bet}, no trump'), array(
                    'player_name' => $players[ $bestBidder ]['player_name'],
                    'biddingTeam' => (self::isTeamA($bestBidder)) ? "Team A" : "Team B",
                    'bet' => $bid['bid_value'],
                    'trump' => 'No trump'
                ) );  

                $this->gamestate->changeActivePlayer($bestBidder);
                self::giveExtraTime($bestBidder);
                $this->gamestate->nextState('newTrick');
            }

        } else {

            // otherwise, move onto the next player to continue bidding
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('makeBid');
        }
    }

    function stNewTrick() {
        // New trick: active the player who wins the last trick, or the player who own the club-2 card
        // Reset trick color to 0 (= no color)
        self::setGameStateInitialValue('trickColor', 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == 4) {
            // This is the end of the trick
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_color = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue('trickColor');
            $currentTrumpColor = self::getGameStateValue('handColor');
            $trumpHasBeenPlayed = false;

            $points = 1;
            foreach ( $cards_on_table as $card ) {

                // points rules for the special cards
                if (self::isThreeSpades($card)) { $points = $points - 3; }
                if (self::isFiveHearts($card)) {$points = $points + 5; }

                // Note: type = card color
                // determine the best card in the trick's suit, if trump has not been played 
                if ($card ['type'] == $currentTrickColor && !$trumpHasBeenPlayed) {
                    if ($best_value_player_id === null || $card ['type_arg'] > $best_value) {
                        $best_value_player_id = $card ['location_arg']; // Note: location_arg = player who played this card on table
                        $best_value = $card ['type_arg']; // Note: type_arg = value of the card
                        $best_color = $card ['type'];
                    }
                // if trump has been played, that's the only number that matters now
                // added extra check to skip trump rules if trump was led
                } else if ($currentTrickColor != $currentTrumpColor && $card['type'] == $currentTrumpColor) {
                    $trumpHasBeenPlayed = true;
                    // reset best value to trump value, in case your trump is lower than what's been played.
                    $best_value = 0;
                    if ($best_value_player_id === null || $card ['type_arg'] > $best_value) {
                        $best_value_player_id = $card ['location_arg']; // Note: location_arg = player who played this card on table
                        $best_value = $card ['type_arg']; // Note: type_arg = value of the card
                        $best_color = $card ['type'];
                    }
                }
            }

            if( $best_value_player_id === null ) {
                throw new feException( self::_("Error, nobody wins the trick") );
            }

            // save points gained/lost in this trick
            self::dump('trick points: ', $points);
            self::dump('trick winner is in Team A: ', self::isTeamA($best_value_player_id));
            if (self::isTeamA($best_value_player_id)) {
                $currentHandPoints = self::getGameStateValue('teamA_hand_points');
                self::setGameStateInitialValue('teamA_hand_points', $currentHandPoints + $points); 
        
            } else {
                $currentHandPoints = self::getGameStateValue('teamB_hand_points');
                self::setGameStateInitialValue('teamB_hand_points', $currentHandPoints + $points);
            }

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer( $best_value_player_id );
            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            //  before we move all cards to the winner (during the second)
            $players = self::loadPlayersBasicInfos();
            $trumpText = ($trumpHasBeenPlayed || ($currentTrickColor == $currentTrumpColor)) ? "(trump)" : "";
            $bid = self::getCurrentBid();

            $trumpValue = ($currentTrumpColor == 0) ? 'no trump' : $this->colors [$currentTrumpColor] ['name'];
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick with ${card_value}${card_color} ${trumpText} for ${points} points'), array(
                'player_id' => $best_value_player_id,
                'player_name' => $players[ $best_value_player_id ]['player_name'],
                'card_value' => $this->values_label [$best_value],
                'card_color' => $this->colors [$best_color] ['name'],
                'trumpText' => $trumpText,
                'points' => $points,
                'trump' => $trumpValue,
                'bet' => $bid['bid_value'],
                'teama' => self::getGameStateValue('teamA_hand_points'),
                'teamb' => self::getGameStateValue('teamB_hand_points')
            ) );          

            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $best_value_player_id
            ) );

            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndHand() {
        
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $teamAHandPoints = self::getGameStateValue('teamA_hand_points');
        $teamBHandPoints = self::getGameStateValue('teamB_hand_points');

        // check who made the bid
        $bestBidder = self::getGameStateValue('bestBidder');
        $bestBid = self::getGameStateValue('bestBid');

        // the team that made the bid gets a special calculation
        // the team that didn't made the bid just gets their total so don't modify
        if (self::isTeamA($bestBidder)) {
            $teamAHandPoints = self::calculateBidResult($teamAHandPoints, $bestBid);
        } else {
            $teamBHandPoints = self::calculateBidResult($teamBHandPoints, $bestBid);
        }


        $player_to_points = array ();
        foreach ( $players as $player_id => $player ) {
            if (self::isTeamA($player_id)) {
                $player_to_points [$player_id] = $teamAHandPoints;
            } else {
                $player_to_points [$player_id] = $teamBHandPoints;
            }
        }

        $min_bid = 7;
        $maxScore = 52 - $min_bid;

        // Apply scores to player
        foreach ( $player_to_points as $player_id => $points ) {
            if ($points != 0) {

                if (self::playerIsOnBidWinningTeam($player_id)) {
                    
                    self::dbIncScore($player_id, $points);
                    self::notifyAllPlayers("points", clienttranslate('${player_name} gets ${nbr} points'), array (
                            'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                            'nbr' => $points ));
                } else {
                    // non-bidding team can only score up to $maxScore
                    $currentScore = self::dbGetScore($player_id);

                    if ($currentScore == $maxScore) {
                    // if already at max score, just inform them what they would've scored but dont' adjust score
                        self::notifyAllPlayers("points", clienttranslate('${player_name} scored ${nbr} points, but hit the max score of ${max}'), array (
                            'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                            'nbr' => $points,
                            'max' => $maxScore
                        ));
                    } else if ($currentScore + $points >= $maxScore) {
                    // non-bidders can't increase score past $maxScore
                        self::dbSetScore($player_id, $maxScore);
                        self::notifyAllPlayers("points", clienttranslate('${player_name} scored ${nbr} points, but hit the max score of ${max}'), array (
                                'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                                'nbr' => $points,
                                'max' => $maxScore
                             ));
                    } else {
                    // otherwise, just add the pts!
                        self::dbIncScore($player_id, $points);
                        self::notifyAllPlayers("points", clienttranslate('${player_name} gets ${nbr} points'), array (
                                'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                                'nbr' => $points ));
                    }
                }
            } else {
                // No point lost (just notify)
                self::notifyAllPlayers("points", clienttranslate('${player_name} did not gain any points'), array (
                        'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'] ));
            }
        }
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );

        ///// Test if this is the end of the game
        foreach ( $newScores as $player_id => $score ) {
            if ($score >= 52) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            } 
            if ($score <= 52) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;                
            }
        }

        // change the dealer to be the player after the dealer
        $currentDealerID = self::getGameStateValue("dealerPlayerID");
        $this->gamestate->changeActivePlayer( $currentDealerID );
        $currentDealerID = self::activeNextPlayer();
        self::setGameStateInitialValue('dealerPlayerID', $currentDealerID); 

        // change active player to be the player after the dealer => he's the one who bids first next hand
        $this->activeNextPlayer();
        
        $this->gamestate->nextState("nextHand");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
