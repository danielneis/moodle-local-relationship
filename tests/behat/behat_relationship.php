<?php

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

class behat_relationship extends behat_base {
    /**
     * Goes to specified URL
     * @Given /^I go to "([^"]*)"$/
     */
     public function iGoTo($url) {
         try{
             $session = $this->getSession();
             $session->visit($url);
         } catch(required_capability_exception $e){
             echo "Exceção: " . $e;
         }
     }

     /**
      *@AfterStep
      */
     public function after($event) {
         $exception = $event->getException();
     }

    /**
    * Click on the element with the provided xpath query
    *
    * @When /^I click on the element with xpath "([^"]*)"$/
    */
    public function iClickOnTheElementWithXPath($xpath)
    {
        $session = $this->getSession(); // get the mink session
        $element = $session->getPage()->find('xpath',
                   $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)); // runs the actual query and returns the element

        // errors must not pass silently
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate XPath: "%s"', $xpath));
        }
        // ok, let's click on it
        $element->click();
    }
}

