{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- ThreeSpot implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    threespot_threespot.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->
<div id="handinfo_div" class="whiteblock">
    <h3>{HAND_INFO}</h3>
    <div id="handinfo_wrap"></div>
    <div id="trickcount_wrap"></div>
</div>

<div id="playertables">

    <!-- BEGIN player -->
    <div class="playertable whiteblock playertable_{DIR}">
        <div class="playertablename" style="color:#{PLAYER_COLOR}">
            {PLAYER_NAME}
        </div>
        <div class="playertablecard" id="playertablecard_{PLAYER_ID}">
        </div>
    </div>
    <!-- END player -->

</div>

<div id="myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="myhand">
    </div>
</div>

<script type="text/javascript">

// Javascript HTML templates
var jstpl_cardontable = '<div class="cardontable" id="cardontable_${player_id}" style="background-position:-${x}px -${y}px">\
                        </div>';

var jstpl_handinfo = '<div id="handinfo">Bidding team: ${biddingTeam}<br/>Bet: ${bet}<br/>Trump: ${trump}<br/></div>';
var jstpl_trickcount = '<div id="trickcount"><br/>Team A points won this hand: ${teama}<br/>Team B points won this hand: ${teamb}<br/></div>';
var jstpl_player_board = '<div class="cp_board" id="cp_p${id}"><span id="team_p${id}">${team}</span><br/><span id="dealer_p${id}">${dealer}</span></div>';
var jstpl_pb_dealer = '<span id="dealer_p${id}">${dealer}</span>';
/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/

</script>  

{OVERALL_GAME_FOOTER}
