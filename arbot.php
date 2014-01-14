<pre>
<?php>
////////////////////////////////////////////////////////////////////
//Programs
////////////////////////////////////////////////////////////////////
//A - Currency Arbitrage, Choose UtoU or BtoB arbitrage through L
//B - Portfolio Balancing, Maintain 50% of two currencies at all times
//C - Custom trailing/advancing sell/sell bot for gna
//D - Buy Down, Buy 1/2 spread each .5 drop and sell 1 each $1 gain
//E - Rolling sell/sell on dips and spikes V1,1.1,1.2,V2,2.1
//F - 24 Hour high/low balancing bot, maintain (high-last)/(high-low) balance in USD and (last-low)/(high-low) in BTC
//G - EMA triggered rolling with Fibonacci shares and high/low balancing combination with micro-trade re-balancing.
//H - Custom Rolling bot based on dynamic thresholds
//I - Micro-Trade rolling bot with variable buy and sell orders and iterations
//J - Revamped micro-rebalance engine added as option - balances based on distance to high low

////////////////////////////////////////////////////////////////////
//Advance and Tail last price with orders ( commision + profit/2 )
//Set order amounts to min < all < max
//Buy as price falls
//Sell as price rises
//USE triple EMA + last to determine trends
//USE distance from high/low to determine funds to sell/buy
//NOTE Buy at last - threshold ( amount is min ( maxtrade, rebalance percentage )
//NOTE num orders is set for buy and sell, use $incriment for order difference
//NOTE 100 orders sell would be price1+.01+.01 etc
//Don't sell unless last > EMA1 > EMA2 > EMA3
//Don't buy unless last < EMA1 < EMA2 < EMA3
//Use micro-orders to play on spikes and dips

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//USER CONFIGURABLE
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//FEATURES
$DEBUG = TRUE; //Should the program Print Debug information
$simulate = FALSE; //RUNS IN SIMULATION MODE, NO TRADES ARE MADE just PRINT OUTPUT
$exchange = "G"; //Pick an exchange to run on
if ($exchange == "G") {$GOX = TRUE;$BTCE = FALSE;}
if ($exchange == "E") {$BTCE = TRUE;$GOX = FALSE;}

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//ENGINE Selection
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
$ENGINE = "M";
//H - Hybrid Engine
//E - EMA engine
//S - Static Engine
//B - Filled Engine
//FF - 50/50 Balance Engine
//JOSH - JOSH's Trading Engine
//JUDE - JUDE's Trading Engine
//M - Micro-roll engine
//F

$HYBRID = $STATIC = $EMA = $BALANCE = $JOSH = $FF = $F = FALSE;
switch ($ENGINE) {
	case "H":
		$HYBRID = TRUE;
		break;
	case "E":
		$EMA = TRUE;
		//EMA times to use
		$ema1Time = 5;
		$ema2Time = 11;
		$ema3Time = 22;
		$ema1M = 2 / ( $ema1Time +1 );
		$ema2M = 2 / ( $ema2Time +1 );
		$ema3M = 2 / ( $ema3Time +1 );
		break;
	case "S":
		$STATIC = TRUE;
		break;
	case "M":
		$MICRO = TRUE;
		////////////////////////////////////////////////////////////////////
		//MICRO-TRADE SETTINGS
		//array(0.023360861,0.035041292,0.040881508,0.055482046,0.06862253,0.089793311,0.113519186,0.146552904,0.186795638,0.239950723); //0,1,1.5,N1+N2/2,N2+N3/2... Modified Fibonacci shares 10 rounds.
		/*
		method fib - calculates fibonacci shares
		1,2,3,5,8,13 multiplier X = 1/(sum of all numbers)*{series X}
		 
		method fib tame - calculates fibonacci shares with a divisor for n3+.
		$microBuyAmountMultiplier = engineMultiplier('fibtame',4,$microBuyDivisor);
		n1=1,n2=n1+n1/x,n3=n1+n2/x,etc.
		 
		method pow - exponential increase by iteration
		2^x,2^x+1
		 
		method random - calculates random numbers 
		
		method double - doubles the amount each order
		1,2,4,8,16
		
		method cos - makes an arc using iteration and cos
		 
		method acos - makes a reverse arc using iteration and acos
		 
		 method s arc, start at .5 move up to 1 move down to .5 move up for the remainder
		 4 points, 1 is half, 2 is full, 3 is half, 4 is last which should be tame,

		*/
		$microSellDivisor = 10; //number of Sell orders to place
		$microBuyDivisor = 10; //Number of Buy orders to place
		$microIteration = .5;//USD$ iteration for multiple orders
		$microAmount = .01; //Amount of BTC per mini buy/sell round
		$microProfit = .005; //The profit threshold, this is the minimum profit per trade to make,
		$microStopLoss = 1; //The stop loss percent, Sell out at a loss
		$microReBuy = .10; //The price change to rebuy after a loss
		$microBuyAmountMultiplier = engineMultiplier('double',2,$microBuyDivisor);
		
		if ($GOX){
		$microSellDivisor = 10; //number of Sell orders to place
		$microBuyDivisor = 10; //Number of Buy orders to place
		$microIteration = .5;//USD$ iteration for multiple orders
		$microAmount = .01; //Amount of BTC per mini buy/sell round
		$microProfit = .004; //The profit threshold, this is the minimum profit per trade to make,
		$microStopLoss = 1; //The stop loss percent, Sell out at a loss
		$microReBuy = .1; //The price change to rebuy after a loss
		$microBuyAmountMultiplier = engineMultiplier('rand',2,$microBuyDivisor);
		}
		
		$microSellCancel = TRUE; //Cancel orders every sell or keep old sell orders?
		////////////////////////////////////////////////////////////////////
		//NOT USER CONFIGURABLE
		$engineProfit = $microProfit;
		$engineAmount = $microAmount;
		$engineSellDivisor = $microSellDivisor;
		$engineBuyDivisor = $microBuyDivisor;
		$countMicroBuy = $countMicroSell = 1;
		$countMicroBuyTotal = $countMicroSellTotal = 0;
		$microBuy = $microSell = FALSE;
		$microThreshold = .60; // dynamic threshold defined arbitrarily.
		$microTicker = 0; //This stores the last price used to calculate mini-trades
		break;
	case "B":	
		$BALANCE = TRUE;
		$balanceBuy = $balanceSell = FALSE;
		$balanceAmount = .025; //Amount of micro-balance trades
		$balanceThreshold = .05; // .05 = 5% //Rebalancing leway - set to 1 to disable
		break;
	case "FF":
		$BALANCE = TRUE; 
		$FF = TRUE;
		break;
	case "JOSH":
		$JOSH = TRUE;
		$joshAmount = 5; //In BTC
		$joshThreshold = 5; //In USD
		$joshStopLoss = .05; //as a percentage
		$joshReBuy = .05; //as a percentage
		break;
	case "JUDE":
		$JUDE = TRUE;
		break;
}

//Stop Loss
$stop = array('target','ticker','over','under','count' => 0);

//Maximum play amounts (that's play not investment) this is after all technically a beta idea

$maxUSD = 500; //The maximum amount of USD to use -> includes B held * last price!
$maxBTC = 1; //The maximum amount of BTC to use

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//Main Trade Engine Settings
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
$amountTrade = .256; //maxpertrade current engine 20130530
$sellDivisor = 3; //The number of sell orders to place 
$buyDivisor = 3; //The number of buy orders to place
$tradeIteration = .10; //If not using compound interest, the amount to differ between trades when using sell/buy divisor above 1
$compoundInterest = FALSE; //Use $incriment or profit^(1+x)
$threshold = .60; //The price change threshold UNUSED as this is set later.
$profitTrade = .025; //The profit threshold, this is the minimum profit per trade to make,
$thresholdOrders = 0; //If using order based triggers vs static or EMA


$maxPendingOrders = 0; //Keep this many orders active when canceling
$maxOrders = 100;//$maxOrders = max orders to pull should be set?  100?

//Send email reports when orders are placed
$emailRCPTo = "SET IN KEY.PHP INCLUDE FILE";
$btceKey = ''; // your API-key
$btceSecret = ''; // your Secret-key

$emailSubject = "BTCbot Trade on {$exchange}";
 
//Time between runs 
$nanosleep = 500000000; //nanoseconds
$sleepCancel = 500000; //nanoseconds to sleep between order cancellations
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//END USER CONFIGURABLE
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////

$minBidBTC = .01; //Minimum Bids the exchange will accept (BTC-E Mins)
$engineAmount = ( min($microAmount/$microSellDivisor,$microAmount/$microBuyDivisor) > $minBidBTC ? $microAmount : $minBidBTC );
//DEBUG need to add multiplier array lowest to  this


if ($exchange == "E") $exchangeCommision = .002; //The exchange commision percent (as 1 - commision as decimal) 
if ($exchange == "G") $exchangeCommision = .0001; //Gox Commision

//BTC-E Currency pairs & bid ask JSON names
$btcesymbol = array("btc_usd", "ltc_btc", "ltc_usd");
$btcebidorask = array("bids", "asks");
if (@$BTCE) $exchangeBuySell = array("buy","sell");
if (@$GOX) $exchangeBuySell = array("bid","ask");

$executeTrade = $postTrade = FALSE;
$SELL = $BUY = TRUE;
$DOT = $STOPLOSS = FALSE; //pretty output \n bool

//COUNTERS
$countIteration=1; //Iteration Counter
$countOrder = 0; //Order counter
$countError = 0; //Error counter
$countDuplicate = 0; //Duplicate last price counter
$countBuys = $countSells = 0; //The buy rate counter / incriments per buy rate so this isn't the number of buy orders but the number of buy rates tried
$countTrades = 0; //Trade execution counter
$countTickers = 0;
$b = $s = 0;
$countInValid = $countValid = 0;
$countCancelled = $countFilledBuy = $countFilledSell = $countFilled = $countFilledIterationBuy = $countFilledIterationSell = 0;

// Tickers
$ticker = $tickerLast = $tickers = array();

//get the incriment from our file
$nonce = readInc();// + 1;

//order cancel timer
$startTime = time();
$dateStart = date('Ymd H:i:s');


////////////////////////////////////////////////////////////////////
//Calculate $microBuyAmountMultiplier $engineMultiplier
////////////////////////////////////////////////////////////////////
function engineMultiplier($method=NULL,$iteration=NULL,$divisor=NULL)
{
        switch ($method)
        {
                case "rand":
                        for ($i=1;$i<=$divisor;$i++){
                                $array[$i] = rand(1,$iteration);
                        }
                        $return = engineMultiplierPercentage($array);

                break;
                case "fib":
                        $first = 1; $second = 2;
                        $array[0] = $first;
                        $array[1] = $second;
                        for ($i=2;$i<=$divisor+1;$i++){
                                $final = $first + $second;
                                $first = $second;
                                $second = $final;
                                $array[$i] = $final;
                        }
                        $return = engineMultiplierPercentage($array);
                break;
                case "fibtame":
                        $first = 1; $second = $first + $first/$iteration;
                        $array[0] = $first;
                        $array[1] = $second;
                        for ($i=2;$i<=$divisor+1;$i++){
                                $final = $first + $second/$iteration;
                                $first = $second;
                                $second = $final;
                                $array[$i] = $final;
                        }
                        $return = engineMultiplierPercentage($array);
                break;
	        case "exp":
			$array[1] = 1;
                        $array[2] = 2;
                        for ($i=3;$i<=$divisor;$i++){
                                $array[$i] = pow($i,$iteration);
                        }
                        $return = engineMultiplierPercentage($array);
                break;
		case "double":
			$array[0] = 1;
                        $array[1] = 2;
                        for ($i=2;$i<=$divisor;$i++){
                                $array[$i] = $array[$i-1]*2;
                        }
                        $return = engineMultiplierPercentage($array);
		break;
                case "cos":
			//DEBUG fix so array 0 exists.
                        $array[1] = -1.5;
                        //print_r($array);
                        for ($i=3;$i<=$divisor+1;$i++){
                                $array[$i-1] = abs(cos($array[1] + ( (3/($divisor-1)) * ($i-2) )));
                        }
                        $array[1] = abs(cos(-1.5));
                        //print_r($array);
                        $return = engineMultiplierPercentage($array);
                break;		
        }
        return $return;
}
function engineMultiplierPercentage($array)
{
        $percentage = 1/array_sum($array);
        foreach ($array as $key => $value)
        {
                $return[$key] = $value * $percentage;
        }
        return $return;
}

	
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////Begin Infinite Loop.////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
while ( 'the answer to life the universe and everything' != '41'){
	//include('key.php');
	writeInc($nonce); //Write the nonce variable
	$countIteration++; //incriment the iteration counter
	$countDuplicate = $countOrder = $countError = 0; // New run reset the duplicate counter
	$executeTrade = FALSE; //reset trade BOOL for next run
	
	$return = $orderList = $TradeHistory = $rate = $amount = $type = array(); //remove previous values from trade arrays
////////////////////////////////////////////////////////////////////
//Poll data
////////////////////////////////////////////////////////////////////
        $tickerOld['ticker']['last'] = @$ticker['ticker']['last']; //change the ticker to the last ticker
	$ticker = array();
        $sleep=rand(1, 10); //set a random sleep timer
	while ( empty($ticker) ){
		if (@$GOX) {
			$ticker = json_decode(send( 'https://data.mtgox.com/api/2/BTCUSD/money/ticker' ), TRUE );
			$ticker['ticker']['last'] = $ticker['data']['last']['value'];
		}
		if (@$BTCE) $ticker = json_decode(send( 'https://btc-e.com/api/2/btc_usd/ticker' ), TRUE );
		if( !@$ticker ){
			print_r($return);
			$ticker = $return = $orderList = $TradeHistory = array();
			//$sleep=rand(6, 36)*(++$countError);
			$countError++;
			print "\nError pulling data.  Error #: {$countError} \n";
			sleep(5);
			continue;
		}  
////////////////////////////////////////////////////////////////////
//Duplicate Ticker
                if ( $tickerOld['ticker']['last'] == $ticker['ticker']['last'] ){
			$ticker = $return = $orderList = $TradeHistory = array();
			if ( $countDuplicate == 59 ? print "\n." : print "." );
			if ( $countDuplicate == 59 ? $countDuplicate = 0 : NULL );
			$DOT = TRUE;
			$countDuplicate++;
			//Static Timer
			time_nanosleep(0, $nanosleep);
			continue;
		}
////////////////////////////////////////////////////////////////////
//Calculate  SMA & EMA
		if ( $ticker['ticker']['last'] > 0 && $tickerOld['ticker']['last'] !== $ticker['ticker']['last'] && $EMA ){ //the later test of dup ticker should never be tested.
			$countTickers++;
			$tickers[$countTickers] = $ticker['ticker']['last'];
			$ema1 = $ema2 = $ema3  = NULL; //= $countTickers
			$ema1 = array_sum( array_slice($tickers, -1, 1, true)) * $ema1M  + (array_sum (array_slice($tickers, ($ema1Time*-1)-1, $ema1Time, true))/$ema1Time) *(1-$ema1M);
			$ema2 = array_sum( array_slice($tickers, -1, 1, true)) * $ema2M  + (array_sum (array_slice($tickers, ($ema2Time*-1)-1, $ema2Time, true))/$ema2Time) *(1-$ema2M);
			$ema3 = array_sum( array_slice($tickers, -1, 1, true)) * $ema3M  + (array_sum (array_slice($tickers, ($ema3Time*-1)-1, $ema3Time, true))/$ema3Time) *(1-$ema3M);
			
			if ( $countTickers < $ema1Time + 1 ){//&& $EMA) {
				if (@$DEBUG && $DOT) {print "\n"; $DOT = FALSE; }
				print "Building EMA1 - Waiting for ";
				print ($ema1Time + 1) - $countTickers;
				print " more tickers\n ";
			} elseif ( $countTickers < $ema2Time + 1 ){//&& $EMA) {
				if (@$DEBUG && $DOT) {print "\n"; $DOT = FALSE; }
				print "Building EMA2 - Waiting for ";
				print ($ema2Time + 1) - $countTickers;
				print " more tickers\n ";
			} elseif ( $countTickers < $ema3Time + 1 ){//&& $EMA) {
				if (@$DEBUG && $DOT) {print "\n"; $DOT = FALSE; }
				print "Building EMA3 - Waiting for ";
				print ($ema3Time + 1) - $countTickers;
				print " more tickers\n ";
			} 
			if ( $countTickers < $ema3Time + 1 ) { //If we haven't built ema3 and we are using ema
				$tickerOld['ticker']['last'] = $ticker['ticker']['last'];
				$ticker = $return = $orderList = $TradeHistory = array();
				continue; //restart the loop until we build emas
			}
			//IF we wont execute a trade, print the ticker and then continue
			//if (
			//($ticker['ticker']['last'] >= $microTicker - $microThreshold && $ticker['ticker']['last'] <= $microTicker + $microThreshold) //We won't execute a microtrade
			//&& ($EMA !(($ema1 > $ema2 && $ema2 > $ema3 ) && $SELL) // We won't buy
			//		&& !(($ema1 < $ema2 && $ema2 < $ema3 ) && $SELL )//We won't sell
			//		) //END EMA
			//		|| ( $BALANCE 
			//		&& $currentBalanceUSD-$reBalanceUSD > 0
			//		&& $currentBalanceBTC-$reBalanceBTC > 0)
			//		)//END BALANCE
			//		
			//		&& $tickerOld['ticker']['last'] !== $ticker['ticker']['last'] //The ticker isn't a dup, (Should never be tested here)
			//		&& $ticker['ticker']['last'] > 0 //The ticker is valid (should ever be tested here)
			//		&& $countIteration > 2 ){ //This is a valid iteration
			//	$tmp4 = round($ticker['ticker']['last'],2); //round the ticker
			//	if ( $countDuplicate >= 59-6 ) {
			//		for ($countDuplicate=$countDuplicate; $countDuplicate < 59 ; $countDuplicate++) {
			//			print ".";
			//		}
			//		print "\n";
			//		$countDuplicate = 0;
			//	}
			//	print "{$tmp4}";
			//	$countDuplicate = $countDuplicate + 6;
			//	$tickerOld['ticker']['last'] = $ticker['ticker']['last'];
			//	$ticker = $return = $orderList = $TradeHistory = array();
			//	$tmp1 = $tmp2 = $tmp3 = $tmp4 = NULL;
			//}
			
		}//IMPLIED ELSE continue processing
	}//END 	while ( empty($ticker) )
////////////////////////////////////////////////////////////////////
//Poll All data for trading	
	//reset the data points and pull all at once (GOX should be an exception because of rate limiting.)
	$ticker = $return = $orderList = $TradeHistory = array(); //blank the tickers
	while( empty($ticker) || empty($return) || empty($orderList) || empty($TradeHistory) ){
		if ($BTCE){
			$ticker = json_decode(send( 'https://btc-e.com/api/2/btc_usd/ticker' ), TRUE ); // BTCE Ticker
			$orderList = json_decode(btce_query("OrderList", array("active" => 1, "pair" => "btc_usd")), TRUE);
			$TradeHistory = json_decode(btce_query("TradeHistory", array("count" => $maxOrders, "pair" => "btc_usd")), TRUE);
			$return = json_decode(btce_query('getInfo'), TRUE); //BTCE account info
		}
		if ($GOX){
			$ticker = json_decode(send( 'https://data.mtgox.com/api/2/BTCUSD/money/ticker' ), TRUE ); //GOX Ticker
			$ticker['ticker']['last'] = $ticker['data']['last']['value'];
			$ticker['ticker']['buy'] = $ticker['data']['buy']['value'];
			$ticker['ticker']['sell'] = $ticker['data']['sell']['value'];
			$ticker['ticker']['high'] = $ticker['data']['high']['value'];
			$ticker['ticker']['low'] = $ticker['data']['low']['value'];
			$return = json_decode(mtgox_query('BTCUSD/money/info'), TRUE); //GOX account Info
			( $return['result'] == "success" ? $return['success'] = 1 : $return['success'] = 0); //Translate GOX to BTCE
			$return['return']['funds']['btc'] = $return['data']['Wallets']['BTC']['Balance']['value'];//Translate GOX to BTCE
			$return['return']['funds']['usd'] = $return['data']['Wallets']['USD']['Balance']['value'];//Translate GOX to BTCE
			$orderList = json_decode(mtgox_query('BTCUSD/money/orders'), TRUE);
			$TradeHistory = json_decode(mtgox_query('BTCUSD/money/trades/fetch'), TRUE); //GOX account Info
			$TradeHistory['return'] = $TradeHistory['data'];
			unset($TradeHistory['data']);
			foreach ( $TradeHistory['return'] as $key => $value){
				$TradeHistory['return'][$key]['type'] = $TradeHistory['return'][$key]['trade_type'];
				$TradeHistory['return'][$key]['rate'] = $TradeHistory['return'][$key]['price'];
				$TradeHistory['return'][$key]['order_id'] = $key; //PHP Notice:
			}
		}
		//Verify the ticker changed
		if( !@$ticker || !@$return || !@$orderList || !@$TradeHistory || @$return['success'] !== 1 ){
			print_r($return);
			$ticker = $return = $orderList = $TradeHistory = array();
			//$sleep=rand(6, 36)*(++$countError);
			$countError++;
			print "\nError pulling data.  Error #: {$countError} \n";
			sleep(5);
		}
	}
	//Translate Gox data to btc-e Format
	if (@$GOX){
		$orderList['success'] = ( $orderList['result'] == "success" ? $orderList['success'] = 1 : $orderList['success'] = 0);
		$orderList['return'] = array();
		$orderList['return'] = $orderList['data'];
		$orderList['data'] = NULL;
	}
			
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//Setup Thresholds for kicking off trades
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
	if ( $ticker['ticker']['last'] > 1 ){ //if the ticker is valid
		if (@$GOX) {
			if ( $return['data']['Trade_Fee'] > 0 ) $exchangeCommision = $return['data']['Trade_Fee']/100; //Gox Commision
		}
		$threshold = ceil( $ticker['ticker']['last']*( (1/(1-$exchangeCommision)/(1-$profitTrade/2)) -1) * 100) /100 ;
		$microThreshold = ceil( $ticker['ticker']['last']*( (1/(1-$exchangeCommision)/(1-$microProfit/2)) -1) * 100) /100 ;
		if (!@$microTicker || @$microTicker < 1 ) {
			$microTicker = $ticker['ticker']['last'];
			$microThresholdBuy = $microTicker + $microThreshold; //Buy Above Last to create trailing buys - Micro-Dip
			$microThresholdSell = $microTicker - $microThreshold; //Sell below last to create advancing sells - Micro-Spike
		}
		if (!@$thresholdBuy || @$thresholdBuy < 1 ) $thresholdBuy = $ticker['ticker']['last'] + $threshold; //We set buy orders when the price increases (trailing buy)
		if (!@$thresholdSell || @$thresholdSell < 1 ) $thresholdSell = $ticker['ticker']['last'] - $threshold; //We set sell orders when the price decreases (advancing sell)
	} else { exit; }

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//Calculate Cost, Balances, and print summary of information.
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//DEBUG need to calculate cost in $sellDivisor iterations for amounts that will be traded.
//DEBUG REFERENCE FILLED ORDERS! Prevent calculating cost based on filled sell orders!  
	$BTCO = $USDO = 0;
	
	$sellOrders = $buyOrders = array(); //blank the order books for each new run (Infinite Loop issue)
	$b = $s = 0; //FIXED order clearing if no open orders
	if ( $orderList['success'] > 0 ){
		foreach ( $orderList['return'] as $key => $value){
			if (@$GOX){	
				$tmp1 = $value['amount']['value'];
				$value['amount'] = NULL;
				$value['amount'] = $tmp1;
				
				$value['rate'] = $value['price']['value'];
				$tmp1 = NULL; $tmp1 = $value['amount'] * $value['rate'];
				$tmp1 = NULL;
			}
			if ( $value['type'] == $exchangeBuySell[1] ){
				$BTCO = $BTCO + $value['amount']; //BTC = sum of sell orders and amounts //PHP Fatal error:  Unsupported operand types in /arbot/buydownbotBTCG.php on line 281
				$sellOrders[$s] = $value['rate'];
				$s++;
			}
			//if ( $value['type'] == $exchangeBuySell[1] && $value['amount'] == $engineAmount ){
			//	$reserveBTC = ( $reserveBTC - $value['amount'] >= 0 ? $reserveBTC - $value['amount'] : 0); //
			//	
			//}
			if ( $value['type'] == $exchangeBuySell[0] ) {
				$USDO = $USDO + $value['amount'] * $value['rate']; //USD = sum of buy orders amount*rate
				$buyOrders[$b] = $value['rate'];
				$b++;
			}
			//if ( $value['type'] == $exchangeBuySell[0] && $value['amount'] == $engineAmount) {
			//	//FIXED reserve usd wasnt decreasing
			//	$reserveUSD = ( $reserveUSD - $value['amount'] * $value['rate']  >= 0 ? $reserveUSD - $value['amount'] * $value['rate'] : 0 ); //USD = sum of buy orders amount*rate
			//}
		}
		/*if ( $countIteration < 3 && $ORDERS ){
			rsort($buyOrders); //ReverseSort orders and discard keys
			sort($sellOrders); //Sort orders and discard keys
			if ( !@$thresholdBuy ) $thresholdBuy =  ( count($sellOrders) <= $thresholdOrders ? @$sellOrders[$countOrder] : @$sellOrders[$thresholdOrders] ); //Fill Xth order before trading
			if ( !@$thresholdSell ) $thresholdSell =  ( count($buyOrders) <= $thresholdOrders ? @$buyOrders[$countOrder] : @$buyOrders[$thresholdOrders] ); //Fill Xth order before trading
		}*/
		//SOLVED this is set in the threshold section now.
	} else {
		if ( $orderList['error'] !== "no orders" ) {
			print_r($orderList);
			}
		}
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
// Calculate FUNDS
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
	$USDB = $BTCB = $USDT = $BTCT = $BTC = $USD = 0;
	//Calculate the TOTAL we have in orders and balance and USD/last BTCTotal
	if ($GOX) $BTCT = $return['return']['funds']['btc'] + $return['return']['funds']['usd']/$ticker['ticker']['last'];
	if ($BTCE) $BTCT = $return['return']['funds']['btc'] + $BTCO + $return['return']['funds']['usd']/$ticker['ticker']['last'] + $USDO/$ticker['ticker']['last'];
	//Calculate the TOTAL USD we have in buy orders, sell orders, BTC balance, USD Balance
	if ($GOX) $USDT = ($return['return']['funds']['btc'])*$ticker['ticker']['last'] + $return['return']['funds']['usd'];
	if ($BTCE) $USDT = ($return['return']['funds']['btc'] + $BTCO)*$ticker['ticker']['last'] + $USDO + $return['return']['funds']['usd'];
	//Calculate the BALANCE we have in orders and funds BTCBalance USDBalance
	if ($GOX) $BTCB = $return['return']['funds']['btc'];
	if ($BTCE) $BTCB = $return['return']['funds']['btc'] + $BTCO;
	if ($GOX) $USDB = $return['return']['funds']['usd'];
	if ($BTCE) $USDB = $return['return']['funds']['usd'] + $USDO;
	//Calculate the TRADE amount we have in funds and orders BTCTrade USDTrade
	$BTC = ( $BTCB >= $maxBTC ? $maxBTC - $BTCO  : $BTCB - $BTCO );
	$USD = ( $USDB >= $maxUSD ?  $maxUSD - $USDO   : $USDB - $USDO );

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
// Calculate BTC Cost of BTCBalance
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
		$dollarCostTotal = $dollarCostShares = $idealSellRate = $idealSellAmount = $dollarCostAverage = $countBuys = 0;
		$countFilledIterationBuy = $countFilledIterationSell = $countFilledIteration = 0;
		$costBalance = array( "total" => 0, "shares" => 0, "average" => 0, "ideal" => 0);
		$costFilled = array( "total" => 0, "shares" => 0, "average" => 0, "ideal" => 0);
		$profitFilled = array( "total" => 0, "shares" => 0, "average" => 0, "ideal" => 0);

		$BTCremaining = $BTCB;
		if ($BALANCE) $BTCremaining = $BTC*($currentBalanceBTC-$reBalanceBTC);
		
		foreach ( $TradeHistory['return'] as $key => $value){
		//DEBUG use this information to calculate trades/costs/profits
			if ( $value['type'] == $exchangeBuySell[0] && $BTCremaining >= $minBidBTC  ) { 
				$costBalance['total'] = $costBalance['total'] + $value['rate']*$value['amount'];
				$costBalance['shares'] = $costBalance['shares'] + $value['amount'];//$amountTrade[0]; Total shares analysed not / spread
				$BTCremaining = $BTCremaining - $value['amount'];
				$countBuys++;
			}
			/*
			{
	"success":1,
	"return":{
		"166830":{
			"pair":"btc_usd",
			"type":"sell",
			"amount":1,
			"rate":1,
			"order_id":343148,
			"is_your_order":1,
			"timestamp":1342445793
		}
	}
}*/
//DEBUG gox compatibility
			if ( @$ordersPlaced && array_key_exists ( $value['order_id'] , $ordersPlaced )){ //PHP Notice:  Undefined index: order_id in /arbot/buydownbotBTCG.php on line 532
				$filledOrders[$value['order_id']] = array(); //Define the array 
				$filledOrders[$value['order_id']] = $ordersPlaced[$value['order_id']]; //Store the order info in the array
				$ordersFilledIteration[$countIteration-1] = array(); //Define the last iteration array
				$ordersFilledIteration[$countIteration-1][$value['order_id']] = array(); //Define the last iteration array
				$ordersFilledIteration[$countIteration-1][$value['order_id']] = $ordersPlaced[$value['order_id']]; //Store the order info in the array
				unset($ordersPlaced[$value['order_id']]);
				$countFilled++;$countFilledIteration++;
				
				if ($value['type'] == $exchangeBuySell[0]){
					$costFilled['total'] = $costFilled['total'] + $value['rate']*$value['amount'];
					$costFilled['shares'] = $costFilled['shares'] + $value['amount'];//$amountTrade[0]; Total shares analysed not / spread
					$countFilledBuy++;$countFilledIterationBuy++;
				}
				if ($value['type'] == $exchangeBuySell[1]){
					$profitFilled['total'] = $profitFilled['total'] + $value['rate']*$value['amount'];
					$profitFilled['shares'] = $profitFilled['shares'] + $value['amount'];//$amountTrade[0]; Total shares analysed not / spread
					$countFilledSell++;$countFilledIterationSell++;
				}
			}		
		}
		if (@$costBalance['shares'] > 0){
			$costBalance['average'] = $costBalance['total']/$costBalance['shares']; //Total cost / Number of shares is purchase price
			$costBalance['ideal'] = ( ($costBalance['total'])/(1-$exchangeCommision)/(1-$exchangeCommision)/(1-$engineProfit) )/$costBalance['shares'];//Average cost + UtoB commision + BtoU Commision is 0% profit sell rate
			$stop['target'] = $costBalance['ideal']*.998*.998*(1-$microStopLoss);
		} else $stop['target'] = 0;
		if (@$costFilled['shares'] > 0){
			$costFilled['average'] = $costFilled['total']/$costFilled['shares']; //Total cost / Number of shares is purchase price
			$costFilled['ideal'] = ($costFilled['average'])/(1-$exchangeCommision)/(1-$exchangeCommision)/(1-$engineProfit);//Average cost + UtoB commision + BtoU Commision is 0% profit sell rate
		}
		
		
		
		

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
// Primary output header block
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
	$message = "\n";
	if (@$DEBUG && $DOT) {print "\n"; $DOT = FALSE; }
	print "\n\n\n\n\n";
	print "**********************************************************\n"; //Print Separator
	print "----------------------------------------------------------\n"; //Print Separator
	print "**********************************************************\n"; //Print Separator
	print "  Started:{$dateStart}\n";
	//$dateCurrent = date('Ymd H:i:s');
	print "  Current:".date('Ymd H:i:s')."\n"; //Print Separator
	$elapsed = time() - $startTime;
	$tmp1 = $elapsed/ 86400 % 1;
        $tmp2 = $elapsed/ 3600 % 24;
        $tmp3 = $elapsed/ 60 % 60;
        $tmp4 = $elapsed % 60;
	print "  Runtime: {$tmp1}:Days {$tmp2}:H {$tmp3}:M {$tmp4}:S\n";
	print "----------------------------------------------------------\n"; //Print Separator
	//$tmp1 = round($stop['target'],2);
	$tmp1 = ( $STOPLOSS ? "ACTIVE" : ( $stop['target'] == 0 ? "NO INVESTMENT" : round($stop['target'],2)) );
	print "Iteration: {$countIteration} Ticker:\${$ticker['ticker']['last']} *****StopLoss:{$tmp1}*****\n"; //Print Separator
	print "----------------------------------------------------------\n"; //Print Separator
	print "---------------- Trade Target Information ----------------\n";		   	
	print "Threshold: Fees:{$exchangeCommision}% Trades:{$threshold} Micro:{$microThreshold}\n";
	if (@$ORDERS || @$STATIC) print "Targets:  Over:\${$thresholdBuy} Last:\${$ticker['ticker']['last']} Under:\${$thresholdSell}\n";
	if ($MICRO && !$STOPLOSS ) print "Micro-Targets: Over:\${$microThresholdBuy} Tick:\${$microTicker} Under:\${$microThresholdSell}\n";
	if ($STOPLOSS) print "Rebuy Targets: Over:{$stop['over']} Tick:{$stop['ticker']} Under:{$stop['under']}\n";
	print "----------------------------------------------------------\n"; //Print Separator
	print "------------------- Funds  Information -------------------\n";
	$tmp1 = round($BTCT,2);$tmp2 = round($USDT,2);
	print " Cash Out: BTC:{$tmp1} USD:\${$tmp2}\n";
	$tmp1 = round($BTCB,2);$tmp2 = round($USDB,2);
	print "  Balance: BTC:{$tmp1} USD:\${$tmp2}\n";
	print " Maximums: BTC:{$maxBTC} USD:\${$maxUSD}\n";
	
	 
	$engineAmount = max($BTCB,$USDB/$ticker['ticker']['last']);
	$engineAmount = min ($engineAmount,$microAmount);
	$engineAmount = round($engineAmount,8);
	$tmp1 = round($engineAmount,2);
	//DEBUG not compatible with other engines, needed to create a tradeAmount or a buyamount and sellamount.

	
	print " EngineAmount:{$tmp1} Sells:{$engineSellDivisor} Buys:{$engineBuyDivisor} Profit:{$engineProfit}\n ";

	$tmp1 = round($BTC,2);$tmp2 = round($USD,2);
	print " Tradable: BTC:{$tmp1} USD:\${$tmp2}\n";
	print "----------------------------------------------------------\n"; //Print Separator
	print "------------------- Order Information  -------------------\n";		    			   

       print "     Open: Buys:{$b} Sells:{$s}\n";
	print "   Placed:Valid:{$countValid} Invalid:{$countInValid} Cancelled:{$countCancelled}\n";
	print "Completed: Buys:{$countFilledBuy} Total:{$countFilled} Sells:{$countFilledSell} \n";
	print " Last Run: Buys:{$countFilledIterationBuy} Total:{$countFilledIteration} Sells:{$countFilledIterationSell}\n";	
	print "----------------------------------------------------------\n"; //Print Separator
	print "------------------- Cost Information  -------------------\n";

	if (@$costBalance['shares'] > 0){
		$tmp1 = round($costBalance['total'],2);
		$tmp2 = round($costBalance['shares'],2);
		$tmp3 = round($costBalance['average'],2);
		$tmp4 = round($costBalance['ideal'],2);
		print "Balance Costs: Total:{$tmp1} Shares:{$tmp2} Average:{$tmp3} Ideal:{$tmp4}\n";
		$tmp1 = $tmp2 = $tmp3 = $tmp4 = NULL;
	} else $costBalance['ideal'] = $ticker['ticker']['last'] * 2;
	if (@$costFilled['shares'] > 0){
		$tmp1 = round($costFilled['total'],2);
		$tmp2 = round($costFilled['shares'],2);
		$tmp3 = round($costFilled['average'],2);
		$tmp4 = round($costFilled['ideal'],2);
		print "Order Costs: Total:{$tmp1} Shares:{$tmp2} Average:{$tmp3} Ideal:{$tmp4}\n";
		$tmp1 = $tmp2 = $tmp3 = $tmp4 = NULL;
	}
	if ( (@$costFilled['shares'] == 0) && (@$costBalance['shares'] == 0)){
		print "Order Costs: Not holding any BTC! \n";
	}
	
	print "----------------------------------------------------------\n"; //Print Separator
	
	print "------------------- Profit Information  -------------------\n";
	if ( $countIteration < 3 ){
		$profit = array('initial' => array('balanceBTC' => $BTCB,'balanceUSD' => $USDB,'ticker' => $ticker['ticker']['last'], 'totalBTC' => $BTCT, 'totalUSD' => $USDT ) );
	}
	//
	
	$profit['buy'] = array('amountBTC' => 0, 'amountUSD' => 0);
	$profit['sell'] = array('amountBTC' => 0, 'amountUSD' => 0);
	if (@$filledOrders){
		foreach ( $filledOrders as $key => $value ){
			if ( $value['type'] == $exchangeBuySell[0] ){
				$profit['buy']['amountBTC'] = $profit['buy']['amountBTC']  + $value['amount'];
				$profit['buy']['amountUSD'] = $profit['buy']['amountUSD'] + $value['rate'] * $value['amount'];
			}
			if ( $value['type'] == $exchangeBuySell[1] ){
				$profit['sell']['amountBTC'] = $profit['sell']['amountBTC']  + $value['amount'];
				$profit['sell']['amountUSD'] = $profit['sell']['amountUSD'] + $value['rate'] * $value['amount'];
			}
			
		}
		//FIXED PHP Warning:  Division by zero in /arbot/buydownbotBTC.php on line 659
		if ( $profit['buy']['amountBTC'] > 0 ) $profit['buy']['cost'] = round($profit['buy']['amountUSD']/$profit['buy']['amountBTC'],2);
		if ( $profit['sell']['amountBTC'] > 0 ) $profit['sell']['cost'] = round($profit['sell']['amountUSD']/$profit['sell']['amountBTC'],2);	
	}
				
	//Initial Balance
	$tmp1 = round($profit['initial']['balanceBTC'],2);
	$tmp2 = round($profit['initial']['balanceUSD'],2);
	print "Initial Balance: BTC:{$tmp1} USD:{$tmp2} Rate:{$profit['initial']['ticker']}  \n";
	
	//Current Balance
	$tmp1 = round($BTCB,2);
	$tmp2 = round($USDB,2);
	print "Current Balance: BTC:{$tmp1} USD:{$tmp2} Rate:{$ticker['ticker']['last']}\n";
	
	//Difference
	$tmp1 = round($USDB - $profit['initial']['balanceUSD'],2);
	$tmp2 = round($BTCB - $profit['initial']['balanceBTC'],2);
	$tmp3 = round(($ticker['ticker']['last'] - $profit['initial']['ticker']),2);
	print "     Difference: BTC:{$tmp2} USD:{$tmp1} Rate:{$tmp3}\n";
	

	if (@$profit['buy']['amountBTC'] > 0){
		//BTC purchased {amountBTC} {costBTC} {totalcost}
		$tmp1 = round($profit['buy']['amountBTC'],2);
		$tmp2 = round($profit['buy']['cost'],2);
		$tmp3 = round($profit['buy']['amountUSD'],2);
		print "     Trade Buys: BTC:{$tmp1} Rate:{$tmp2} Total:{$tmp3}\n";
	}
	if (@$profit['sell']['amountBTC'] > 0){
		//BTC sold      {amountBTC} {costBTC} {totalprofit}
		$tmp1 = round($profit['sell']['amountBTC'],2);
		$tmp2 = round($profit['sell']['cost'],2);
		$tmp3 = round($profit['sell']['amountUSD'],2);
		print "    Trade Sells: BTC:{$tmp1} Rate:{$tmp2} Total:{$tmp3}\n";	
	}
	//Gains
	//sell - buy + BTCholdings*last*commision - initialBTCbalance*initialLast*commision
	$tmp1 = round($profit['sell']['amountUSD'] - $profit['buy']['amountUSD'] + ($BTCB*$ticker['ticker']['last']*.998) - ($profit['initial']['balanceBTC']*$profit['initial']['ticker']*.998),2);
	$tmp2 = round($USDT - $profit['initial']['totalUSD'] - $tmp1,2);
	print "     Profit From Trading:{$tmp1}  \n";
	print "        Unrealized Gains:{$tmp2}  \n";
	//BTC Appreciation = start btc * start rate - startbtc * current rate
	$tmp1 = round($profit['initial']['totalBTC'] * $profit['initial']['ticker'],2);
	$tmp2 = round($profit['initial']['totalBTC'] * $ticker['ticker']['last'],2);
	$tmp4 = round($tmp1 - $tmp2,2);
	print " BTC Appreciation: Start:{$tmp1} Current:{$tmp2} Difference:{$tmp4}\n";
	
	//Run Value = profit - appreciation
	//$tmp1 = round($profit['initial']['balanceBTC']*$profit['initial']['ticker']*.998 + $profit['initial']['balanceUSD'],2);
	$tmp3 =  round($USDT - $profit['initial']['totalUSD'],2);
	print "  Program Value:{$tmp3}\n";
	$message = $message . "  Program Value:{$tmp3}\n";
	$tmp1 = $tmp2 = $tmp3 = $tmp4 = 0;
	print "----------------------------------------------------------\n"; //Print Separator

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////TRADE ENGINES/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////		


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////Micro-Roll ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//DEBUG $stop target ticker over under count
//SOLVED Need to record amounts and rates to prevent cancellation 
//DEBUG Need to set a stop loss to cancel all orders, sell all B and restart (use main trade engine)
//SOLVED Need to prevent duplicate rate orders
//SOLVED Need to use MINI for this section and MICRO for balance trades or Micro for this and balance for balance trades.
//SOLVED you should never set sell orders if you don't have buy orders, you should always have buy orders,
//SOLVED you shouldn't need to set rate at threshold*count,
//SOLVED just use duplicate rate protection and set to iteration higher each time.
 	if ($MICRO){
		print "------------------- Micro-Trade Block -------------------\n";
		
		//print "    Stop-Loss: Under:\${$microThresholdSell}\n";
		//print "    Stop-Loss: Over:\${$microThresholdBuy} Tick:\${$microTicker} Under:\${$microThresholdSell}\n";
		print "Checking Micro-Trades: ";
		if ($STOPLOSS){
			if ( $ticker['ticker']['last'] >  $stop['over'] || $ticker['ticker']['last'] < $stop['under'] ){
				$STOPLOSS = FALSE;
				$REBUY = TRUE;
				//Rebuy orders
				print "\n *********************************************** \n";
				print "\n *****Stop Loss in Lifted - Trades Resumed ***** \n";
				print "\n *********************************************** \n";
				$microTicker = $ticker['ticker']['last'];
				$microThresholdBuy = $microTicker + $microThreshold;//$rate[$countOrder - floor($tmp1/2)]; //array_sum($tmp0) / count($tmp0); //Fill an order first $microTicker + $microThreshold;//*$countMicroSell;//Trailing Buy orders, if this sell order fills, Buy More
				$microThresholdSell = $microTicker - $microThreshold; //Sell again if last drops below to create advancing sells - Micro-Spike
				print "New-Micro-Targets: Over:\${$microThresholdBuy} Tick:\${$microTicker} Under:\${$microThresholdSell}\n";
			} else {
				print "\n *********************************************** \n";
				print "\n ****** Stop Loss Active - Trades Frozen ******* \n";
				print "\n *********************************************** \n";
			}
		}
		if ( $ticker['ticker']['last'] < $stop['target'] && !$STOPLOSS ){
			$STOPLOSS = TRUE; $REBUY = FALSE;
			print "\n *********************************************** \n";
			print "\n ***** Stop Loss Activated - Trades Frozen ***** \n";
			print "\n *********************************************** \n";
			$stop = array('target' ,'ticker' => $ticker['ticker']['last'],'over' => $ticker['ticker']['last']*(1+$microReBuy) ,'under' => $ticker['ticker']['last']*(1-$microReBuy),'count' => $stop['count']++ );
			print_r($stop);
			print "DEBUG THIS SECTION OF CODE";
			print "BTC: COST: LAST:\n";
			print "Rebuy Targets: Over:{$stop['over']} Tick:{$stop['ticker']} Under:{$stop['under']}\n";
		}
		//$stop['target'] = $costBalance['ideal']*.998*.998*(1-$microStopLoss);
		$microBTCRemaining = $BTCB;
		$microUSDRemaining = $USDB;
////////////////////////////////////////////////////////////////////
// First run cancel, buy and sell
		if ( $countIteration < 3 && !$STOPLOSS ){
			print "First Run Trades.\n";
			$microTicker = $ticker['ticker']['last'];
			//First Run, Cancel Orders
			cancelOrders($exchangeBuySell[0]);
			cancelOrders($exchangeBuySell[1]);
			//First Run, Check USD, BTC, costBalance, place buy and sell.
//DEBUG check funds for buy and sell trades.
			if ( $BTCB >= $minBidBTC ){
				$executeTrade = TRUE; //turn on trading
				$tmp1 = 0;
				$microSellPrice = max($costBalance['ideal'],$ticker['ticker']['last']);
				while ( $tmp1 <= ($BTCB/$engineAmount)*$microSellDivisor && $microBTCRemaining >= $minBidBTC ){
					$type[$countOrder] = $exchangeBuySell[1];
					$rate[$countOrder] = $microSellPrice + .01*$tmp1;
					$rate[$countOrder] = ceil($rate[$countOrder]*100)/100;
					$amount[$countOrder] = max($BTCB/(($BTCB/$engineAmount)*$microSellDivisor),$minBidBTC); //sell everything
					$amount[$countOrder] = floor($amount[$countOrder]*pow(10,8))/pow(10,8);
					//$amount[$countOrder] = min($amount[$countOrder],$BTCB);
					$amount[$countOrder] = ($BTCB < $amount[$countOrder] ? $BTCB : $amount[$countOrder] ); //if we don't have that much B
					print "Micro-Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}\n";
					$microBTCRemaining = $microBTCRemaining - $amount[$countOrder];
					$countOrder++;$tmp1++;
				}
				$countMicroSellTotal++;
			}
			if ( $USDB >= $minBidBTC*$ticker['ticker']['last'] ){
				$executeTrade = TRUE; //turn on trading
				$tmp1 = 0;
				while ( $tmp1 <= $microBuyDivisor && $microUSDRemaining >=  ($ticker['ticker']['last']-$microThreshold-$microIteration*$tmp1)*$minBidBTC ){
					$type[$countOrder] = $exchangeBuySell[0];
					$rate[$countOrder] = $ticker['ticker']['last'] - $microThreshold - $microIteration*$tmp1;
					$rate[$countOrder] = floor($rate[$countOrder]*100)/100;


					$amount[$countOrder] = max( ($engineAmount*$microBuyAmountMultiplier[$tmp1]) ,$minBidBTC);
					$amount[$countOrder] = floor($amount[$countOrder]*pow(10,8))/pow(10,8);
					print "Micro-Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}\n";
					$microUSDRemaining = $microUSDRemaining - $amount[$countOrder] * $rate[$countOrder];
					$countOrder++;$tmp1++;
				}
			$countMicroBuyTotal++;	
			}
			
		} 
////////////////////////////////////////////////////////////////////
// Stop Loss and Rebuy
		if ( $STOPLOSS ){



			if ( $BTCB > $minBidBTC && $STOPLOSS ) { //in case we lifted stop loss above.
				cancelOrders($exchangeBuySell[0]);
				cancelOrders($exchangeBuySell[1]);
				print "\n *********************************************** \n";
				print "\n **** Stop Loss Activated - Liquidating BTC **** \n";
				print "\n *********************************************** \n";
				
				$tmp1 = $tmp2 = 0;
				while ( $tmp1 <= $microSellDivisor ){
					$executeTrade = TRUE;
					$type[$countOrder] = $exchangeBuySell[1];
					$rate[$countOrder] = $ticker['ticker']['last'] - $microThreshold - $microIteration*$tmp1;
					$rate[$countOrder] = floor($rate[$countOrder]*100)/100;
					//If we cancel orders first, we don't need repeat order protection
					//while ( in_array($rate[$countOrder],$sellOrders) ){
					//	$rate[$countOrder] = $ticker['ticker']['last'] - $microThreshold - $microIteration*$tmp2;
					//	$rate[$countOrder] = ceil($rate[$countOrder]*100)/100;
					//	$tmp2++;
					//}
					$amount[$countOrder] = max($BTCB/$microSellDivisor,$minBidBTC);
					$amount[$countOrder] = floor($amount[$countOrder]*pow(10,8))/pow(10,8);
					print "Micro-Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}\n";
					$BTC = $BTC - $amount[$countOrder];
					$countOrder++;$tmp1++;
				}
				$tmp1 = $tmp2 = 0;
			}
		}
////////////////////////////////////////////////////////////////////
// Micro-Trade Engine Primary	
		if ($countIteration > 2 && !$STOPLOSS ){ // && !$REBUY){ DEBUG Haven't build rebuy code yet, use primary engine
			$countOrder = 0;
			//If ticker is above Buy or Below Sell or we filled an order (Needs to be a buy order!)
			if ( $countFilledIterationSell > 0 || $ticker['ticker']['last'] > $microThresholdBuy || $ticker['ticker']['last'] < $microThresholdSell || $countFilledIterationBuy > 0 || $b == 0 || $BTC > $minBidBTC || $ticker['ticker']['last'] >= $costBalance['ideal']) {

				if ( $BTC > $minBidBTC || $USDB >= ($ticker['ticker']['last'] - $microThreshold)*$minBidBTC || $countFilledIterationBuy > 0 ){
					//FIXED change USD test from micro amount to minbidbtc.
					//FIXED changed micro, test, from btc > amount to btc > minbid to sell after an error.
					if ( $countFilledIterationBuy > 0 ) print "Buy Order Filled.\n";
					if ( $USDB >= ($ticker['ticker']['last'] - $microThreshold)*$engineAmount ) print "USD Funds Available {$USDB}.\n";
					if ( $BTC > $minBidBTC ) print "BTC Funds Available {$BTC}.\n";
					if ( $b == 0 ) print "No open buy orders.\n"; //FIXED added B == 0 will rebuy after stoploss.
					if ( $ticker['ticker']['last'] > $microThresholdBuy || $ticker['ticker']['last'] < $microThresholdSell) print "Threshold Crossed.\n";
					if ( $ticker['ticker']['last'] >= $costBalance['ideal'] ) print "Balance Ideal sell price crossed.  Selling.\n";
					//Iteration: 170 Ticker:$119.682 *****StopLoss:118*****
					//Balance Costs: Total:118.1 Shares:0.99 Average:118.95 Ideal:119.67
					//Checking Micro-Trades: No Trades. 


////////////////////////////////////////////////////////////////////
// Sell Code Block
//DEBUG changed all amounts to BTC from BTCB as we are not cancelling orders here

					if ( $BTC > $minBidBTC || ($ticker['ticker']['last'] >= $costBalance['ideal'] && $costBalance['ideal'] > 0) || $ticker['ticker']['last'] < $microThresholdSell){ //Sell
//----------------------------------------------------------
//------------------- Cost Information  -------------------
//Order Costs: Not holding any BTC! 
//----------------------------------------------------------
//------------------- Profit Information  -------------------
//Initial Balance: BTC:0 USD:580.06 Rate:121.26682  
//Current Balance: BTC:0 USD:580.06 Rate:121.44665
//     Difference: BTC:0 USD:0 Rate:0.18
//     Profit From Trading:0  
//        Unrealized Gains:0  
// BTC Appreciation: Start:580.06 Current:580.92 Difference:-0.86
//  Program Value:0
//----------------------------------------------------------
//------------------- Micro-Trade Block -------------------
//Checking Micro-Trades: USD Funds Available 580.05604.
//Balance Ideal sell price crossed.  Selling.
						print "\n";		
						if ($countFilledIterationBuy > 0 && !$microSellCancel ) { //We filled orders last run
							NULL;
							//Set amount
							$microSellAmount = $costFilled['shares']*.998;//Lol i put total //DEBUG Needs to be reduced for commision?
							$microSellAmount = $BTC; //My .998 multiplier was acurate to to many decimals?  its either this or floor.
							//Set price
							$microSellPrice = $costFilled['ideal'];
							
						}  elseif ($ticker['ticker']['last'] >= $costBalance['ideal'] || $microSellCancel) {// We didn't fill orders but have BTC or want to cancel
//------------------- Micro-Trade Block -------------------
//Checking Micro-Trades: USD Funds Available 2202.166858729.
//Threshold Crossed.
//
//Canceling Orders
//
//Cancelled 0 sell Orders 
							cancelOrders($exchangeBuySell[1]);
							//keep amount
							$microSellAmount = $BTCB; //We always sell all BTC available
							//set price
							$microSellPrice = $costBalance['ideal'];
						} else {
							$microSellAmount = $BTC;
							$microSellPrice = $costBalance['ideal'];
						}
						///// SELL TRADE /////
						$tmp1 = $tmp2 = 0;
						//DEBUG FIXED change <= to < for sell divisor, if 1 order is requested the initial value is 0 so it must be less than.
						while ( $tmp1 < $microSellDivisor && $microBTCRemaining >= $minBidBTC ){
							$executeTrade = TRUE; //turn on trading
							$microTicker = $ticker['ticker']['last'];
							$type[$countOrder] = $exchangeBuySell[1];
							//$rate[$countOrder] = $ticker['ticker']['last'] + $microThreshold*$countMicroSell  + $microIteration*$tmp1;
							//CHANGELOG 31 removed multiplier from rate, added duplicate price protection
							$rate[$countOrder] = $microSellPrice + $microIteration*$tmp1 + $microIteration*$tmp2;
							$rate[$countOrder] = max($microSellPrice + $microIteration*$tmp1 + $microIteration*$tmp2,$ticker['ticker']['last']);
							$rate[$countOrder] = ceil100($rate[$countOrder]);
							while ( in_array($rate[$countOrder],$sellOrders) ){
								$rate[$countOrder] = max($microSellPrice + $microIteration*$tmp1 + $microIteration*$tmp2,$ticker['ticker']['last']);
								$rate[$countOrder] = ceil($rate[$countOrder]*100)/100;
								$tmp2++;
							}
							$amount[$countOrder] = max($microSellAmount/$microSellDivisor,$minBidBTC);
							$amount[$countOrder] = floor($amount[$countOrder]*pow(10,8))/pow(10,8);
							print "Micro-Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}\n";
							$microBTCRemaining = $microBTCRemaining - $amount[$countOrder];
							$countOrder++;$tmp1++;
						}
						//if ( $microBTCRemaining < 0 ){
						//	$amount[$countOrder-1] = $amount[$countOrder-1] + $microBTCRemaining; //Debug last order over amount, not working...
						//	$countOrder--;
						//}	
						$tmp1 = $tmp2 = 0;
						$microBuy = FALSE; //We didn't Buy
						$microSell = TRUE; //We did Sell
						if ($microSell) {$countMicroSell++;$countMicroSellTotal++;$countMicroBuy = 1;} else {$countMicroSell = 1;}
						$microThresholdBuy = ( $rate[0] > 0 ? $rate[0] : $ticker['ticker']['last'] + $microThreshold );

						//array_sum($tmp0) / count($tmp0); //Fill an order first $microTicker + $microThreshold;//*$countMicroSell;//Trailing Buy orders, if this sell order fills, Buy More
						$microThresholdSell = $ticker['ticker']['last'] - $microThreshold; //Sell again if last drops below to create advancing sells - Micro-Spike
						print "New-Micro-Targets: Over:\${$microThresholdBuy} Tick:\${$ticker['ticker']['last']} Under:\${$microThresholdSell}\n";
					} else print "BTC Funds Not Available. \n";
////////////////////////////////////////////////////////////////////
// BUY Code Block
//FIXED no buy orders active If threshold was crossed and we have funds or there are no buy orders and we have funds
//DEBUG TESTING if we filled a sell order replace all buy  orders.
					if ( ($ticker['ticker']['last'] > $microThresholdBuy || $b == 0 || $countFilledIterationSell > 0 ) && $USDB >= ($ticker['ticker']['last'] - $microThreshold)*$minBidBTC  ){ //
						cancelOrders($exchangeBuySell[0]);
						print "\n";
						
						$tmp1 = $tmp2 = 0;
						while ( $tmp1 < $microBuyDivisor && $microUSDRemaining > 0 ){//DEBUG
							$executeTrade = TRUE; //turn on trading
							$microTicker = $ticker['ticker']['last'];
							$type[$countOrder] = $exchangeBuySell[0];
							$rate[$countOrder] = $ticker['ticker']['last'] - $microThreshold - $microIteration*$tmp1;
							$rate[$countOrder] = floor($rate[$countOrder]*100)/100;
							while ( in_array($rate[$countOrder],$buyOrders) ){
								$tmp2++;
								$rate[$countOrder] = $ticker['ticker']['last'] - $microThreshold - $microIteration*$tmp1 - $microIteration*$tmp2;
							}
							$amount[$countOrder] = max($BTCB/(($BTCB/$engineAmount)*$microSellDivisor),$minBidBTC); //sell everything
							$amount[$countOrder] = max( ($engineAmount*$microBuyAmountMultiplier[$tmp1]) ,$minBidBTC);
							$amount[$countOrder] = floor($amount[$countOrder]*pow(10,8))/pow(10,8);
							print "Micro-Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}\n";
							$microUSDRemaining = $microUSDRemaining - $amount[$countOrder] * $rate[$countOrder];
							$countOrder++;$tmp1++;
						}
						
						$microBuy = TRUE;
						$microSell = FALSE;
						if ($microBuy)  {$countMicroBuy++;$countMicroBuyTotal++;$countMicroSell = 1;} else {$countMicroBuy = 1;}
						$microThresholdSell = $ticker['ticker']['last'] - $microThreshold;//$rate[$countOrder - floor($tmp1/2)];// array_sum($tmp0) / count($tmp0);//*$countMicroBuy;//Advancing Sell orders, if this buy order fills, sell more
						$microThresholdBuy = $ticker['ticker']['last'] + $microThreshold; //Buy Above Last to create trailing buys - Micro-Dip
						$tmp0 = $tmp1 = $tmp2 = 0;
						print "New-Micro-Targets: Over:\${$microThresholdBuy} Tick:\${$ticker['ticker']['last']} Under:\${$microThresholdSell}\n";
					} elseif ($ticker['ticker']['last'] <= $microThresholdBuy) {
						print "Buy Threshold not met.\n";
					} elseif ($USD < ($ticker['ticker']['last'] - $microThreshold)*$engineAmount){
						print "USD Funds Not Available. \n";
					}
				} else print "Funds Not Available. \n";
			} else print "No Trades. \n";
			$tmp1 = $countMicroBuy - 1;
			$tmp2 = $countMicroSell - 1;
			print "Micro-Hits  Total: Buys:{$countMicroBuyTotal} Sells:{$countMicroSellTotal}\n";
			print "Micro-Hits Active: Buys:{$tmp1} Sells:{$tmp2}\n";
			$tmp1 = $tmp2 = NULL;
			
		}
	}
	
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////Balance ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//DEBUG you may want to use threshold crossing for these to prevent mass ordering.
	if ( $BALANCE ){
	////////////////////////////////////////////////////////////////////
	//Calculate distance, difference, portfolio balance
		$distanceFromHigh = $ticker['ticker']['high'] - $ticker['ticker']['last'];
		$distanceFromLow = $ticker['ticker']['last'] - $ticker['ticker']['low'];
		$spreadHighLow = $ticker['ticker']['high'] - $ticker['ticker']['low'];
	
		//Calculate new preffered balance amounts
		$reBalanceBTC = floor(($distanceFromLow/$spreadHighLow)*100)/100;
		$reBalanceUSD = 1-$reBalanceBTC; //Prefer USD during rebalance
		
		$currentBalanceBTC = floor(($BTCB/$BTCT)*100)/100;
		$currentBalanceUSD = 1-$currentBalanceBTC; //$USDB/$USDT;
		
		$reBalanceUSDDisplay = $reBalanceUSD*100;
		$reBalanceBTCDisplay = $reBalanceBTC*100;
		$currentBalanceUSDDisplay = $currentBalanceUSD*100;
		$currentBalanceBTCDisplay = $currentBalanceBTC*100;
	
		print "------------ Portfolio Balancing Information  ------------\n";
		$tmp1 = round($distanceFromHigh,2);$tmp2 = round($distanceFromLow,2);$tmp3 = round($spreadHighLow,2);
		print "From High:{$tmp1} From Low:{$tmp2} Spread:{$tmp3}\n";
		$tmp1 = $tmp2 = $tmp3 = NULL;
		
		print "Current Balance: USD:{$currentBalanceUSDDisplay}% BTC:{$currentBalanceBTCDisplay}%\n";
		print "Target  Balance: USD:{$reBalanceUSDDisplay}% BTC:{$reBalanceBTCDisplay}%\n";
	
		////////////////////////////////////////////////////////////////////
		//Setup Buy orders
		$USDremaining = $USD;
		$balanceBuy = FALSE;
		print "------------------- Mini-Balance-Trade Block -------------------\n";
		print "Checking Micro-Balance Buy Funds and Balance: ";
		if ( $BALANCE && ($USDT*($currentBalanceUSD-$reBalanceUSD))/($ticker['ticker']['last'] - $threshold)  > 0 && $balanceSell = FALSE ){ //Trade USD for BTC
			print "Increasing BTC - Decreasing USD. \n";
			$executeTrade = TRUE; //turn on trading
			$balanceBuy = TRUE; //Let the next part know we bought
			$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
			$rate[$countOrder] = floor100($ticker['ticker']['last'] - $threshold);
			$amount[$countOrder] = round( ($USDT*($currentBalanceUSD-$reBalanceUSD))/$rate[$countOrder] ,2); //the amount of the trade
			$amount[$countOrder] = min($amount[$countOrder],$balanceAmount);
			$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
			print "Buying {$amount[$countOrder]}B at a rate of \${$rate[$countOrder]} \${$USDremaining} left.\n";
			$balanceThresholdBuy = $rate[$countOrder];
			$countOrder++;
		} else print "No Buy Trades.\n";
		////////////////////////////////////////////////////////////////////
		//Setup Sell Orders
		$BTCremaining = $BTC;
		$balanceSell = FALSE;
		print "Checking Micro-Balance Sell Funds and Balance: ";
		if ( $BALANCE && $BTCT*($currentBalanceBTC-$reBalanceBTC) > 0  && $balanceBuy = FALSE ){ //Trade BTC for USD
			print "Increasing USD - Decreasing BTC. \n";
			$executeTrade = TRUE; //turn on trading
			$balanceSell = TRUE;
			$type[$countOrder] = $exchangeBuySell[1]; //The type of trade
			$rate[$countOrder] = ceil((($ticker['ticker']['last'] + $threshold))*100)/100;
			$amount[$countOrder] = round( ($BTCT*($currentBalanceBTC-$reBalanceBTC)) ,2); //the amount of the trade
			$amount[$countOrder] = min($amount[$countOrder],$balanceAmount);
			$BTCremaining = $BTCremaining - $amount[$countOrder] ;
			print "Selling {$amount[$countOrder]}B at a rate of \${$rate[$countOrder]} \${$USDremaining} left.\n";
			$thresholdSell = $rate[$countOrder];
			$countOrder++;
		} else print "No Sell Trades.\n";
	}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////JOSH's ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	if ($JOSH){
		//$joshAmount = 5; //In BTC
		//$joshThreshold = 5; //In USD
		//$joshStopLoss = .05; //as a percentage
		//$joshReBuy = .05; //as a percentage
		if ( !@$joshTicker ) $joshTicker =  $ticker['ticker']['last'];
	////////////////////////////////////////////////////////////////////
	//Reset Funds and print Header Block
		$USDremaining = $USD;
		$BTCremaining = $BTC;
		print "------------------- JOSH's ENGINE TRADE BLOCK -------------------\n";
		print "Threshold: Fees:{$exchangeCommision}% Trades:{$joshThreshold}";
		$tmp1 = $joshTicker + $joshThreshold; $tmp2 = $joshTicker - $joshThreshold;
		if (@$DEBUG ) print "Josh-Targets: Over:\${$tmp1} Tick:\${$joshTicker} Under:\${$tmp2}\n";
		print "Checking JOSH Buy Funds and Balance: ";
	//DEBUG need to get last two order ID's to check if they filled?
	////////////////////////////////////////////////////////////////////
	//Reset the ticker
		if ( $ticker['ticker']['last'] > $joshTicker + $joshThreshold || $ticker['ticker']['last'] < $joshTicker - $joshThreshold ){
			$joshTicker =  $ticker['ticker']['last'];
		}
	////////////////////////////////////////////////////////////////////
	//Cancel all orders
		cancelOrders($exchangeBuySell[0]); //cancel current buy orders to replace;
		cancelOrders($exchangeBuySell[1]); //cancel current sell orders to replace;
	
	////////////////////////////////////////////////////////////////////
	//Setup Buy orders
		if ( 'josh' == 'cool' && $USD > $joshAmount*($ticker['ticker']['last']-$joshThreshold) ){ //BUY
			print "\n";
			$executeTrade = TRUE; //turn on trading
			
			$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
			$rate[$countOrder] = floor100($ticker['ticker']['last'] - $joshThreshold);
			$amount[$countOrder] = $joshAmount; //the amount of the trade
			$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
			print "Buying {$amount[$countOrder]}B at a rate of \${$rate[$countOrder]} \${$USDremaining} left.\n";
			$countOrder++;
		} else print "No Buy Trades.\n";
	////////////////////////////////////////////////////////////////////
	//Setup Sell Orders
		if ( 'josh' == 'cool' && $BTC > $joshAmount ){ //SELL
			print "\n";
			$executeTrade = TRUE; //turn on trading
			
			$type[$countOrder] = $exchangeSellSell[1]; //The type of trade
			$rate[$countOrder] = ceil100($ticker['ticker']['last'] + $joshThreshold);
			$amount[$countOrder] = $joshAmount; //the amount of the trade
			$BTCremaining = $BTCremaining - $amount[$countOrder];
			print "Selling {$amount[$countOrder]}B at a rate of \${$rate[$countOrder]} \${$BTCremaining} left.\n";
			$countOrder++;
		} else print "No Sell Trades.\n";
	}
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////STATIC ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//set reference ticker

//buy at ticker -$5
//sell at ticker +$5

//repeat until all funds are used

//wait for a $5 movement

//Do it again


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////Fibonacci ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////EMA ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	if ($EMA){
		print "------------------- EMA ENGINE TRADE BLOCK -------------------\n";
		$tmp1 = round($ema1,2);$tmp2 = round($ema2,2);$tmp3 = round($ema3,2);
		if (@$DEBUG && $EMA ) print "EMAs:      EMA{$ema1Time}:\${$tmp1} EMA{$ema2Time}:\${$tmp2} EMA{$ema3Time}:\${$tmp3} \n"; //&& $EMA 
		$tmp1 = $tmp2 = $tmp3 = NULL;	
	}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////BALANCE ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////Hybrid EMA/FIB/BALANCE ENGINE/////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//Setup Buy orders
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
/*
		$USDremaining = $USD;// + $USDO; //
		//if ( $countOrder > 0 ) $countOrder++;
		print "Checking Buy Funds and Threshold: ";
		if ( ( ($ema1 > $ema2 && $ema2 > $ema3 ) && $BUY && $USDremaining > $minBidUSD*$sharesBuy && !$BALANCE )
		    || ( $BALANCE && ($USDT*($currentBalanceUSD-$reBalanceUSD)) /($ticker['ticker']['last'] - $threshold)  > 0 ) ){ //The price is moving upward, set buy orders
//DEBUG DOES THIS CHANGE WITH EMA CROSSING VS THRESHOLD CROSSING?
			print "Buying. \n";
			$executeTrade = TRUE; //turn on trading
			$SELL = TRUE;$BUY = FALSE; //Only buy after selling and sell after buying
			cancelOrders($exchangeBuySell[0]); //cancel current buy orders to replace;
//DEBUG you should now have your balance including orders in $return
			$countBuys = 1;
			
			
			
			if ($BALANCE){
				$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
				$rate[$countOrder] = floor((($ticker['ticker']['last'] - $threshold))*100)/100;
				$amount[$countOrder] = round( ($USDT*($currentBalanceUSD-$reBalanceUSD))/$rate[$countOrder] ,2); //the amount of the trade
				$amount[$countOrder] = min($amount[$countOrder],$amountTrade);
				$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
				print "Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$USDremaining} left.\n";
				$thresholdBuy = $rate[$countOrder];
				$countBuys++;$countOrder++;
			}
			if (!$BALANCE){
				//First Order
				$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
				$rate[$countOrder] = floor((($ticker['ticker']['last'] - $threshold)*(1-$exchangeCommision))*100)/100;
				$amount[$countOrder] = round(($amountBuyTrade[$countBuys]*$USDremaining)/$rate[$countOrder],2); //the amount of the trade
				$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
				print "Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$USDremaining} left.\n";
				$countBuys++;$countOrder++;
				
				if ( $buyDivisor > 1 ){
					//Second through n-1th order
					while ( $countBuys < $buyDivisor ){
						$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
						$rate[$countOrder] = floor( ($rate[$countOrder-1] - (($distanceFromLow/($buyDivisor+1))*$countBuys) )*100)/100;
						$amount[$countOrder] = round(($amountBuyTrade[$countBuys]*$USDremaining)/$rate[$countOrder],2); //the amount of the trade
						$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
						print "Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$USDremaining} left.\n";
						$countBuys++;$countOrder++;
					}
				}
				
				if ( $buyDivisor > 2 ){
						//Nth (Last) Order
						$type[$countOrder] = $exchangeBuySell[0]; //The type of trade
						$rate[$countOrder] = floor( ($rate[$countOrder-2] - (($distanceFromLow/($buyDivisor+1))*$countBuys) )*100)/100;
						$amount[$countOrder] = floor($USDremaining/$rate[$countOrder]); //the amount of the trade
						$USDremaining = $USDremaining - $rate[$countOrder] * $amount[$countOrder] ;
						print "Buying {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$USDremaining} left.\n";
						$countBuys++;$countOrder++;
				}
				rsort($buyOrders); //ReverseSort orders and discard keys
				sort($sellOrders); //Sort orders and discard keys
				$thresholdSell =  ( count($buyOrders) <= $thresholdOrders ? $buyOrders[$countOrder] : $buyOrders[$thresholdOrders] ); //Fill Xth order before trading
				print "New Targets: Sell:{$thresholdSell} Last:{$ticker['ticker']['last']} Buy:{$thresholdBuy}\n";
			}
		} else print "No Buy Trades.\n";
////////////////////////////////////////////////////////////////////
//Setup Sell Orders
////////////////////////////////////////////////////////////////////
	$BTCremaining = $BTC;
	print "Checking Sell Funds and Threshold: ";
	if ( ( ($ema1 < $ema2 && $ema2 < $ema3 ) && $SELL && $BTCremaining > $minBidBTC*$sharesSell  && !$BALANCE )
	    || ( $BALANCE && $BTCT*($currentBalanceBTC-$reBalanceBTC) > 0 ) ){ //The price is moving upward, set buy orders
		print "Selling. \n";
		$executeTrade = TRUE; //turn on trading
		$SELL = FALSE;$BUY = TRUE; //Only buy after selling and sell after buying
		cancelOrders($exchangeBuySell[1]); //cancel current buy orders to replace;
		$countSells = 1;
		
		if ($BALANCE){
			$type[$countOrder] = $exchangeBuySell[1]; //The type of trade
			$rate[$countOrder] = ceil(($idealSellRate*(1+$profitTrade)*100))/100;
			if ($GOX) $rate[$countOrder] = ceil((($ticker['ticker']['last'] + $threshold))*100)/100;
			$amount[$countOrder] = round( ($BTCT*($currentBalanceBTC-$reBalanceBTC)) ,2); //the amount of the trade
			$amount[$countOrder] = min($amount[$countOrder],$amountTrade);
			$BTCremaining = $BTCremaining - $amount[$countOrder] ;
			print "Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$USDremaining} left.\n";
			$thresholdSell = $rate[$countOrder];
			$countSells++;$countOrder++;
		}
		if (!$BALANCE){
					
			//First Order
			$type[$countOrder] = $exchangeBuySell[1]; //The type of trade
			$rate[$countOrder] = ceil(($idealSellRate*(1+($profitTrade*$countSells)))*100)/100 ; //Compound interest style sell rate http://php.net/manual/en/function.pow.php
			$rate[$countOrder] = max($rate[$countOrder],$ticker['ticker']['last']+$threshold*$countSells);
			$amount[$countOrder] = round(($amountSellTrade[$countSells]*$BTC),2); //the amount of the trade
			$BTCremaining = $BTCremaining - $amount[$countOrder] ;
			print "Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$BTCremaining} left.\n";
			$countSells++;$countOrder++;
			
			if ( $sellDivisor > 1 ){
				//Second through n-1th order
				while ( $countSells < $sellDivisor ){
					$type[$countOrder] = $exchangeBuySell[1]; //The type of trade
					$rate[$countOrder] = ceil(($idealSellRate*(1+($profitTrade*$countSells)))*100)/100 ; //Compound interest style sell rate http://php.net/manual/en/function.pow.php
					$rate[$countOrder] = max($rate[$countOrder],$ticker['ticker']['last']+$threshold*$countSells);
					$amount[$countOrder] = round(($amountSellTrade[$countSells]*$BTC),2); //the amount of the trade
					$BTCremaining = $BTCremaining - $amount[$countOrder] ;
					print "Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$BTCremaining} left.\n";
					$countSells++;$countOrder++;
				}
			}
			
			if ( $sellDivisor > 2 ){
				//Nth (Last) Order
				$type[$countOrder] = $exchangeBuySell[1]; //The type of trade
				$rate[$countOrder] = ceil(($idealSellRate*(1+($profitTrade*$countSells)))*100)/100 ; //Compound interest style sell rate http://php.net/manual/en/function.pow.php
				$rate[$countOrder] = max($rate[$countOrder],$ticker['ticker']['last']+$threshold*$countSells);
				$amount[$countOrder] = $BTCremaining; //the amount of the trade
				$BTCremaining = $BTCremaining - $amount[$countOrder];
				print "Selling {$amount[$countOrder]}B at a rate of {$rate[$countOrder]}U {$BTCremaining} left.\n";
				$countSells++;$countOrder++; 
			}
				
					
	
			//NOTE Trailing buys, as price rises set new buy orders - after filling buy orders set sell orders
			rsort($buyOrders); //ReverseSort orders and discard keys
			sort($sellOrders); //Sort orders and discard keys
			$thresholdBuy =  ( count($sellOrders) <= $thresholdOrders ? $sellOrders[$countOrder] : $sellOrders[$thresholdOrders] ); //Fill Xth order before trading
			//$thresholdSell =  ( count($buyOrders) <= $thresholdOrders ? $buyOrders[$countOrder] : $buyOrders[$thresholdOrders] ); //Fill Xth order before trading
			print "New Targets: Sell:{$thresholdSell} Last:{$ticker['ticker']['last']} Buy:{$thresholdBuy}\n";
		}
	} else print "No Sell Trades.\n";
*/
////////////////////////////////////////////////////////////////////
//Modular Trade Code
////////////////////////////////////////////////////////////////////
//$executeTrade = FALSE;

	if ($simulate) $executeTrade = FALSE;
	if (@$executeTrade ){
		$countTrades = 0;
		while ( $countTrades <= $countOrder ){
			if ( !empty($type[$countTrades]) ){
				if (@$BTCE) $trade = json_decode(btce_query("Trade", array("pair" => "btc_usd", "type" => $type[$countTrades], "amount" => $amount[$countTrades], "rate" => $rate[$countTrades])), TRUE);
				if (@$GOX) $trade = json_decode(mtgox_query("BTCUSD/money/order/add", array('type' => $type[$countTrades], 'amount' => $amount[$countTrades], 'price' => $rate[$countTrades])), TRUE);
				if (@$GOX) ( $trade['result'] == "success" ? $trade['success'] = 1 : $trade['success'] = 0); 
				if ( $trade['success'] !== 1 ) {
					print "Failed: {$type[$countTrades]} {$amount[$countTrades]}BTC at \${$rate[$countTrades]}\n";
					//print "\n Result:";
					print_r($trade);
					print "USD {$USD} B{$USDB} T{$USDT} BTC {$BTC} B{$BTCB} T{$BTCB}\n";
					//print "\n Type:{$type[$countTrades]}";
					//print "\n Amount:{$amount[$countTrades]}";
					//print "\n Rate:{$rate[$countTrades]}\n";
					$countInValid++;
				}
				if ( $trade['success'] == 1 ){
					$countValid++;
					//print "\n Result:";
					//print_r($trade);
					print "Ordered: {$type[$countTrades]} {$amount[$countTrades]}BTC at \${$rate[$countTrades]}\n";
					if (@$GOX) $trade['return']['order_id'] = $trade['data']; 
					$ordersPlaced[$trade['return']['order_id']] = array(
					"type" => $type[$countTrades],
					"amount" => $amount[$countTrades],
					"rate" => $rate[$countTrades]);
					//print "\n orders placed:";
					//print_r($ordersPlaced);
				}
				// The message
				$message = $message . "Type:{$type[$countTrades]} Amount:{$amount[$countTrades]}BTC at Rate:{$rate[$countTrades]}\n";
				if (@$GOX) sleep(rand(1,10));
			}
			$countTrades++;
		}
		$message = $message . "\n BTC Balance:{$BTCB} USD Bal:{$USDB}\n";
		mail($emailRCPTo, $emailSubject, $message);
	}
	$message = NULL;
////////////////////////////////////////////////////////////////////
//END Modular Trade Code
////////////////////////////////////////////////////////////////////

}
	print "\n\n DANGER the answer to life the universe and everything is no longer 42? \n\n";
	exit;
////////////////////////////////////////////////////////////////////
// FUNCTIONS
////////////////////////////////////////////////////////////////////
	
	function floor100( $value = NULL )
	{
		$result = floor($value*100)/100;
		return $result;
	}
	function ceil100( $value = NULL )
	{
		$result = ceil($value*100)/100;
		return $result;
	}
	function btce_query( $method = NULL, $req = array() )
	{
	global $btceKey;
	global $btceSecret;
	global $nonce;
	
	$key = $btceKey;
	$secret = $btceSecret;
		// API settings
		$req['method'] = $method;
		$req['nonce'] = $GLOBALS['nonce']++;

		// generate the POST data string
		$postData = http_build_query($req, '', '&');

		// generate the extra headers
		$headers = array(
					'Sign: '.hash_hmac("sha512", $postData, $secret) 
					,"Key: {$key}"
					);
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		curl_setopt($ch, CURLOPT_URL, 'https://btc-e.com/tapi');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		
		writeInc($GLOBALS['nonce']);
		
		return curl_exec($ch);
	}
	function mtgox_query($path, array $req = array()) {
		$key = ''; //Your API Key
		$secret = ''; //Your API Secret
		$req['nonce'] = $GLOBALS['nonce']++; //Incriment the Global Nonce (Allows for multiple polls per second)
		$post_data = http_build_query($req, '', '&'); // generate the POST data string
	 
		$prefix = $path."\0";
		
		// generate the extra headers
		$headers = array(
			'Rest-Key: '.$key,
			'Rest-Sign: '.base64_encode(hash_hmac('sha512', $prefix.$post_data, base64_decode($secret), true)),
		);
	 
		//Everyone sets this to null and then uses if==null <- It always LOLZ
		$ch = null;
		$ch = curl_init();
		//curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; GOXBOT; '.php_uname('s').'; PHP/'.phpversion().')');
		curl_setopt($ch, CURLOPT_URL, 'https://data.mtgox.com/api/2/'.$path);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	 
		writeInc($GLOBALS['nonce']);
			
		return curl_exec($ch);
	}
	function writeInc( $value = NULL )
	{
		if (@$GLOBALS['BTCE']) $fp = fopen('nonce','w');
		if (@$GLOBALS['GOX']) $fp = fopen('nonceg','w');
		fwrite($fp, $value);
		fclose($fp);
		
		return;
	}
	
	
	function readInc()
	{
		if (@$GLOBALS['BTCE']) $fp = fopen('nonce','r');
		if (@$GLOBALS['GOX']) $fp = fopen('nonceg','r');
		$value = (int)fread($fp, 8);
		fclose($fp);
		
		return $value;
	}
	
	function cancelOrders( $type = NULL ) //modified to only cancel buy orders.
	{
		global $GOX;
		global $BTCE;
		global $exchangeBuySell;
		global $sleepCancel;
		global $engineAmount;
		global $simulate;
		global $maxPendingOrders;
		global $countCancelled;
		global $ordersPlaced;
		
		if (@$GLOBALS['BTCE']) $return = json_decode(btce_query("OrderList"), TRUE);
		if (@$GOX){
			$return = json_decode(mtgox_query('BTCUSD/money/orders'), TRUE);
			( $return['result'] == "success" ? $return['success'] = 1 : $return['success'] = 0);
			$return['return'] = $return['data'];

		}
		if($return['success'] > 0){
			$countCancel=0;
			$tmp2 = 0; print "Canceling Orders\n";
			foreach ($return['return'] as $key => $value){
				$order_id = $key;
				if (@$GOX){
					$tmp1 = $value['amount']['value'];
					$value['amount'] = NULL;
					$value['amount'] = $tmp1;
					$value['rate'] = $value['price']['value'];
					$order_id = $value['oid'];
				}
				if ( $value['type'] == $type && (@$value['pair'] == "btc_usd" || @$value['item'] == "BTC") ){ //$value['amount'] != $engineAmount &&
					if (!$simulate && $countCancel >= $maxPendingOrders ) {
						if ( $tmp2 == 50 ) {print "\nCanceling";$tmp2=0;} else {print ".";$tmp2++;};
						if (@$BTCE) $trade = json_decode(btce_query("CancelOrder", array("order_id" => $order_id)), TRUE);
						if (@$GOX) $trade = json_decode(mtgox_query("BTCUSD/money/order/cancel", array("oid" => $order_id)), TRUE);
						if (@$GOX) ( $trade['result'] == "success" ? $trade['success'] = 1 : $trade['success'] = 0);
					}
					if ($trade['success'] == 1){
						$countCancel++;
						$countCancelled++;
						unset($ordersPlaced[$order_id]); //remove this order from our orders placed list
					}
					time_nanosleep(0,$sleepCancel);
				}
			}
			print "\nCancelled {$countCancel} {$type} Orders \n";
		}
		while ( empty($return) ){
			if (@$GLOBALS['BTCE']) $GLOBALS['return'] = json_decode(btce_query('getInfo'), TRUE);
			if (@$GLOBALS['GOX'])  $GLOBALS['return'] = json_decode(mtgox_query('BTCUSD/money/info'), TRUE);
			time_nanosleep(0,$sleepCancel);
		}
	}
	function send( $url = NULL )
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
//Calculate Fibonacci based shares for buy/sell orders
////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////
/*
	$fibonacciShares = array();
	$fibonacciShares['numbers'] = array(1,2);
	$fibonacciShares['shares'] = array(1,3);
	$fibonacciShares['multiplier'] = array(1,1/3);

	$first = 1; $second = 2;$shares = 0;
	for($i=2;$i<=25;$i++){
		$final = $first + $second;
		$first = $second;
		$second = $final;
		$fibonacciShares['numbers'][$i] = $final;
		$fibonacciShares['shares'][$i] = $final + $fibonacciShares['shares'][$i-1];
		$fibonacciShares['multiplier'][$i] = (1/$fibonacciShares['shares'][$i])*$final;
		print " Number:{$fibonacciShares['numbers'][$i]} Shares:{$fibonacciShares['shares'][$i]} Multiplier:{$fibonacciShares['multiplier'][$i]}\n";
	}



////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////*/
/* MICRO TRADE ENGINE NOTES
 *Buy in arcs, .5 .75 1 .75 .5 abandon
 *buy in spurts at key amounts in increasing spreads
 *last - threshold, last - threshold - fibonacci *1 *2 *3 *5 *8 *13
 *use amountmini/miniselldivisor for while counter < miniselldivisor price - counter 
 *StopLoss in effect, abondon B at stoploss and try again
 *microthresholds change as price falls and rises,
 *sell all microholdings at an advance for a profit - keep buys in array
 *IF a buy is filled calculate the cost,
 *Set a stoploss and a trail amount, once the trail amount is hit place the order, (last will be above price so you can't leave the order)
 *make a micro array to store orders
 *this is more becoming the macro-engine?
 *it still should play micro spikes,
 *the code can be used in a macro-engine though with larger amounts
 */

?>
