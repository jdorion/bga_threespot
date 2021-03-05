<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * ThreeSpot implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * threespot.action.php
 *
 * ThreeSpot main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/threespot/threespot/myAction.html", ...)
 *
 */
  
  
  class action_threespot extends APP_GameAction
  { 
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "threespot_threespot";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 
  	
  	// TODO: defines your action entry points there
    public function playCard() {
      self::setAjaxMode();
      $card_id = self::getArg("id", AT_posint, true);
      $this->game->playCard($card_id);
      self::ajaxResponse();
    } 

    public function makeBid() {
      self::setAjaxMode();
      $bid_id = self::getArg("id", AT_posint, true);
      $this->game->makeBid($bid_id);
      self::ajaxResponse();    
    }

    public function setTrump() {
      self::setAjaxMode();
      $trump_id = self::getArg("id", AT_posint, true);
      $this->game->setTrump($trump_id);
      self::ajaxResponse();        
    }
  }
  