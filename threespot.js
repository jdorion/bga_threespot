/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * ThreeSpot implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * threespot.js
 *
 * ThreeSpot user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.threespot", ebg.core.gamegui, {
        constructor: function(){
            console.log('threespot constructor');
            this.cardwidth = 72;
            this.cardheight = 96;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );
            
            window.gamedataplayers = gamedatas.players;
            // Setting up player boards
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
                
                // Setting up players boards if needed
                var player_board_div = $('player_board_'+player_id);
                dojo.place( this.format_block('jstpl_player_board', player ), player_board_div );
            }
            
            
            // TODO: Set up your game interface here, according to "gamedatas"
            // Player hand
            this.playerHand = new ebg.stock(); // new stock object for hand
            this.playerHand.create( this, $('myhand'), this.cardwidth, this.cardheight );
            this.playerHand.image_items_per_row = 8; // 8 images per row
            //this.playerHand.setSelectionMode(0);
            this.playerHand.setSelectionAppearance('class');

            // Create cards types:
            // have to create the 3 and 5 as 7s here so that the stock model knows where to grab the image
            // it does it by the id, and image_items_per_row, so the 3 has to be id 0 and the 5 has to be id 8.
            // this is just building the card, not really associating it with a value.
            for (var color = 1; color <= 4; color++) {
                for (var value = 7; value <= 14; value++) {

                    // Build card type id
                    var card_type_id = this.getCardUniqueId(color, value);
                    this.playerHand.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards.jpg', card_type_id);
                }
            }

            window.gamedatas = this.gamedatas;

            // Cards in player's hand
            for ( var i in this.gamedatas.hand) {
                var card = this.gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;

                var uniqueId = this.getCardUniqueId(color, value);
                this.playerHand.addToStockWithId(uniqueId, card.id);
            }

            // Cards played on table
            // since the playCardOnTable method just displays a card and it's not part of a stock object, 
            // we have to put extra logic there for the 3 and 5
            for (i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }

            dojo.connect( this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged' );
 
            // hand info
            this.replaceHandInfo(gamedatas.biddingTeam, gamedatas.trump, gamedatas.bet);
            this.replaceTrickCount(gamedatas.teama, gamedatas.teamb);

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
 
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );

            switch( stateName )
            {
                case 'playerTurn':
                    if (this.isCurrentPlayerActive()) {
                        this.makeCardsSelectable(args.args.cards);
                    } else {
                        dojo.query("#myhand .stockitem").removeClass("stockitem_selectable").addClass("stockitem_unselectable");
                    }

                break;
            
                case 'biddingTurn':
                    if (this.isCurrentPlayerActive()) {
                        
                        var bids = this.convertToBidObjects(args.args.bids);
                        window.bids = bids;
                        
                        // TODO: disable pass if dealer (there's no passing bid)
                        if ('1' in bids) {
                            this.addActionButton( 'button_1_id', _('Pass'), 'bidPass' ); 
                        }

                        this.addActionButton( 'button_2_id', _('Bid'), 'bidPopUp' ); 
                    } else {
                        dojo.query("#myhand .stockitem").removeClass("stockitem_selectable").addClass("stockitem_unselectable");
                    }

                break;

                case 'settingTrump':

                    // reset trick count to 0 for both teams
                    this.replaceTrickCount(0,0);

                    if (this.isCurrentPlayerActive()) {
                        var action = "setTrump";

                        window.settingTrumpArgs = args;
                        var suits = args.args.suits;
                        window.suits = suits;

                        this.multipleChoiceDialog(
                            _('Pick trump'), suits,
                            dojo.hitch(this, function (choice) {
                                console.log('choice: ' + choice)
                                window.choice = choice;
                                var suitchoice = suits[choice];
                                window.suitchoice = suitchoice;
                                console.log('dialog callback with ' + choice);
                                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", { id: choice }, this, function (result) { });
                            }));
                    } else {
                        dojo.query("#myhand .stockitem").removeClass("stockitem_selectable").addClass("stockitem_unselectable");
                    }
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {               
/*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

        bidPass : function() {
            var action = "makeBid";
            var passBidId = 1;
            this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", { id: passBidId }, this, function (result) { });
        },

        bidPopUp : function() {
            
            var action = "makeBid";
            var bids = window.bids;

            this.multipleChoiceDialog(
                _("What's your bid?"), bids,
                dojo.hitch(this, function (choice) {
                    //console.log('choice: ' + choice);
                    var bidchoice = bids[choice];
                    window.bidchoice = bidchoice;
                    var bidId = parseInt(bidchoice.bid_id);
                    window.bidId = bidId;
                    //console.log('dialog callback with ' + bids[choice] + ", id: " + bidId);
                    this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", { id: bidId }, this, function (result) { });
                }));
        },

        // Get card unique identifier based on its color and value
        // here we need to check if the card in the hand is the 3 or 5 and change the id
        getCardUniqueId : function(color, value) {
            if (value == 3 && color == 1) { return  0;}
            if (value == 5 && color == 2) { return  8;}
            return (color - 1) * 8 + (value - 7);
        },
        
        makeCardsSelectable(cards){    
            window.mCScards = cards;

            dojo.query("#myhand .stockitem").removeClass("stockitem_selectable").addClass("stockitem_unselectable");
            cards.forEach(cardId => dojo.query("#myhand_item_" + cardId).removeClass('stockitem_unselectable').addClass('stockitem_selectable') );
        },

        playCardOnTable : function(player_id, color, value, card_id) {
            //console.log("playing card on table: player_id: " + player_id + ", color: " + color + ", value: " + value + ", card_id: " + card_id);
            
            // hack for the 3 and 5 so it calculates the correct positioning in the CSS sprite
            var card_value = value;
            if (card_value == 5 || card_value == 3) { card_value = 7; }

            // player_id => direction
            dojo.place(this.format_block('jstpl_cardontable', {
                x : this.cardwidth * (card_value - 7),
                y : this.cardheight * (color - 1),
                player_id : player_id
            }), 'playertablecard_' + player_id);

            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + player_id, 'overall_player_board_' + player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item

                if ($('myhand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + player_id, 'myhand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + player_id, 'playertablecard_' + player_id).play();
        },

        replaceHandInfo : function (biddingTeam, trump, bet) {
            // hand info
            dojo.destroy('handinfo')
            dojo.place(this.format_block('jstpl_handinfo', {
                biddingTeam: biddingTeam,
                trump : trump,
                bet: bet,
            }), 'handinfo_wrap');
        },
        replaceTrickCount : function (teama, teamb) {
            // hand info
            dojo.destroy('trickcount')
            dojo.place(this.format_block('jstpl_trickcount', {
                teama : teama,
                teamb : teamb
            }), 'trickcount_wrap');
        },
        replaceDealerFlag : function (dealerId, playerIds) {
            var id;
            while (id = playerIds.pop()) {

                var dealerText = "";
                if (id == dealerId) {
                    dealerText = "Dealer";
                }
                
                dojo.destroy('dealer_p' + id);
                dojo.place(this.format_block('jstpl_pb_dealer', {
                    id: id,
                    dealer: dealerText
                }), 'cp_p' + id);
            }
        },
        convertToBidObjects : function (bidArray) {
            class Bid {
                constructor(bid) {
                    this.bid_id = bid.bid_id;
                    this.bid_value = bid.bid_value;
                    this.no_trump = bid.no_trump;
                    this.label = bid.label;
                }

                toString() {
                    return this.label;
                }
            }

            var result = {};
            for (var key in bidArray) {
                result[key] = new Bid(bidArray[key]);
            }

            //let result = [];
            //bidArray.forEach((bid) => result.push(new Bid(bid)));
            return result;
        },

        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
       onPlayerHandSelectionChanged : function() {
        var items = this.playerHand.getSelectedItems();

        if (items.length > 0) {
            var action = 'playCard';
            if (this.checkAction(action, true)) {
                // Can play a card
                var card_id = items[0].id;                    
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                    id : card_id,
                    lock : true
                }, this, function(result) {
                }, function(is_error) {
                });

                this.playerHand.unselectAll();
            } else if (this.checkAction('giveCards')) {
                // Can give cards => let the player select some cards
            } else {
                this.playerHand.unselectAll();
            }
        }
    },

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your threespot.game.php file.
        
        */
        setupNotifications: function()
        {
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");
            dojo.subscribe( 'trickWin', this, "notif_trickWin" );
            this.notifqueue.setSynchronous( 'trickWin', 1000 );
            dojo.subscribe( 'giveAllCardsToPlayer', this, "notif_giveAllCardsToPlayer" );
            dojo.subscribe( 'newScores', this, "notif_newScores" );
            dojo.subscribe('bidMade', this, "notif_bidMade");
            dojo.subscribe('trumpSet', this, "notif_trumpSet");
            dojo.subscribe('newDealer', this, "notif_newDealer");
        },  
        
        // TODO: from this point and below, you can write your game notifications handling methods
        notif_newHand : function(notif) {
            // We received a new full hand of 8 cards.
            this.playerHand.removeAll();

            for ( var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },
        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
        },
        notif_trickWin : function(notif) {
            // Update hand info to show updated trick score
            this.replaceTrickCount(notif.args.teama, notif.args.teamb);
        },
        notif_giveAllCardsToPlayer : function(notif) {
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.player_id;
            for ( var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + player_id, 'overall_player_board_' + winner_id);
                dojo.connect(anim, 'onEnd', function(node) {
                    dojo.destroy(node);
                });
                anim.play();
            }
        },
        notif_newScores : function(notif) {
            // Update players' scores
            for ( var player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
        notif_bidMade : function(notif) {
            // nothing for now, could update hand info in future??
        },
        notif_trumpSet : function(notif) {
            // nothing for now, could update hand info in future??
            this.replaceHandInfo(notif.args.biddingTeam, notif.args.suit, notif.args.bet);
        },
        notif_newDealer : function(notif) {
            this.replaceDealerFlag(notif.args.id, notif.args.playerIds);
        },

        /* @Override */
        format_string_recursive : function(log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;
                    
                    // list of special keys we want to replace with images / html
                    var key = 'suit';
                  
                    window.formatStringRecursiveArg = args;
                    if (key in args) {
                        args[key] = this.getTokenDiv(key, args);
                    }
                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },

        getTokenDiv : function(key, args) {
            
            window.tokenkeys = key;
            window.tokenargs = args;

            var color = "red";
            var suit = args[key];
            if (suit == '♣' || suit == '♠') {
                color = "black";
            }

            var tokenDiv = this.format_block('jstpl_log_suit', {
                "suit" : suit,
                "color" : color,
            });
         
            return tokenDiv;

       },
   });             
});
