<?php

/**
 * PageCarton
 *
 * LICENSE
 *
 * @category   PageCarton
 * @package    Application_Wallet_Payment
 * @copyright  Copyright (c) 2020 PageCarton (http://www.pagecarton.org)
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @version    $Id: Payment.php Saturday 29th of August 2020 11:53AM ayoola@ayoo.la $
 */

/**
 * @see PageCarton_Widget
 */

class Application_Wallet_Payment extends Application_Subscription_Checkout_Abstract_HtmlForm
{
	
    /**
     * Access level for player. Defaults to everyone
     *
     * @var boolean
     */
	protected static $_accessLevel = array( 0 );
	
    /**
     * 
     * 
     * @var string 
     */
	protected static $_objectTitle = 'Pay with Wallet'; 

    /**
     * 
     * 
     */
	public static function checkoutEligibility(& $option )
    {
        if( ! $cart = Application_Subscription_Checkout::getStorage()->retrieve() )
        { 
            return false;
        }
        $values = $cart['cart'];

        $totalPrice = 0.00;
        foreach( $values as $name => $value )
        {
            if( $name === 'Add funds to wallet' )
            {
                //  can't use wallet balance to pay for wallet top-up
                return false;
            }
            $totalPrice += floatval( $value['price'] * $value['multiple'] );
            $counter++;
        }

        $totalPrice = $totalPrice;

        $balance = (float) Ayoola_Application::getUserInfo( 'wallet_balance' );
        if( $totalPrice > $balance )
        { 
            return false;
        }
        $currency = ( Application_Settings_Abstract::getSettings( 'Payments', 'default_currency' ) ? : '' );
        $option['checkoutoption_name'] .= ' (' . $currency . $balance . ')';
        return true;
    }
        
    /**
     * Performs the whole widget running process
     * 
     */
	public function init()
    {    

		try
		{ 
            //  Code that runs the widget goes here...
            self::$_apiName = $this->getParameter( 'checkoutoption_name' ) ? : 'Wallet Balance';
            if( ! Ayoola_Application::getUserInfo( 'username' ) )
            { 
                return $this->setViewContent(  '' . self::__( '<span class="boxednews centerednews badnews">You need to login to continue</span>' ) . '', true  );
            }
            if( ! $cart = Application_Subscription_Checkout::getStorage()->retrieve() )
            { 
                return $this->setViewContent(  '' . self::__( '<span class="boxednews centerednews badnews">You have no item in your shopping cart.</span>' ) . '', true  );
            }
            $values = $cart['cart'];

            $parameters = static::getDefaultParameters();
            $parameters['price'] = 0.00;
            foreach( $values as $name => $value )
            {
                if( $name === 'Add funds to wallet' )
                {
                    //  can't use wallet balance to pay for wallet top-up
                    return $this->setViewContent(  '' . self::__( '<span class="boxednews centerednews badnews">Wallet balance can not be topped up with wallet balance</span>' ) . '', true  );
                    return false;
                }
                @$parameters['price'] += floatval( $value['price'] * $value['multiple'] );
                $counter++;
            }

            $parameters['amount'] = ( $this->getParameter( 'amount' ) ? : $parameters['price'] );
    
            $balance = (float) Ayoola_Application::getUserInfo( 'wallet_balance' );
            if( $parameters['amount'] > $balance )
            { 
                return $this->setViewContent(  '' . self::__( '<span class="boxednews centerednews badnews">Wallet balance is not enough for payment</span>' ) . '', true  );
            }
            $currency = ( Application_Settings_Abstract::getSettings( 'Payments', 'default_currency' ) ? : '' );

            $paid = false;
            if( ! empty( $_GET['process'] ) )
            {
                $orderNumber = self::getOrderNumber(  );
				$transferInfo = array();
				$transferInfo['allow_ghost_sender'] = true;
				$transferInfo['to'] = Application_Wallet_Settings::retrieve( 'main_wallet_username' );
				$transferInfo['from'] = Ayoola_Application::getUserInfo( 'username' );
				$transferInfo['amount'] = $parameters['amount'];
                $transferInfo['notes'] = 'Payment for order';
                if( $paid = Application_Wallet::transfer( $transferInfo ) )
                {

                    $response = Application_Subscription_Checkout::changeStatus( array( 'order_status' => 99, 'order_id' => $parameters['order_number'] ) );
                    //    var_export( $response );
                    header( 'Location: ' . $parameters['success_url'] );
                    return true;
                }
                $this->setViewContent( self::__( '<p class="badnews">Payment with wallet balance failed.</p>' ) ); 
                return false;
            }

            //  Output demo content to screen
            $this->setViewContent( '<h2>Pay with ' . Ayoola_Page::getDefaultDomain() . ' wallet</h2><br>' ); 
            $url = Ayoola_Application::getUrlPrefix() . '/widgets/Application_Wallet_Payment?process=1';

            $this->setViewContent( sprintf( '<p>Current Wallet Balance: ' . $currency . '%f</p>', $balance ) ); 

            $this->setViewContent( sprintf( '<p>Wallet Balance After Payment: ' . $currency . ' %f</p>', ( $balance - $parameters['amount'] ) ) ); 

            $this->setViewContent( '<br><a href="' . $url . '" class="pc-btn ">Pay ' . $currency . $parameters['amount'] . ' <i class="fa fa-shopping-cart pc_give_space"></i></a>' );
            // end of widget process
          
		}  
		catch( Exception $e )
        { 
            //  Alert! Clear the all other content and display whats below.
        //    $this->setViewContent( self::__( '<p class="badnews">' . $e->getMessage() . '</p>' ) ); 
            $this->setViewContent( self::__( '<p class="badnews">Theres an error in the code</p>' ) ); 
            return false; 
        }
	}
	// END OF CLASS
}
