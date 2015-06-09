<?php

require_once "vendor/autoload.php";

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Acme\StoreBundle\Event\StoreSubscriber;

class Console
{
    protected $commands;
    protected $defaultCommands;
    protected $options;
    protected $flags;
    protected $arguments;
    protected $dispatcher;
    protected $executeCommand;

    public function __construct( Array $args = array(), Array $commands = array() )
    {
        $this->commands = array();
        $this->defaultCommands = array();
        $this->options = array();
        $this->flags = array();
        $this->arguments = array();

        $this->parseArgs( $args );
    }

    public static function notCallable( Console $console, EventDispatcher $dispatcher )
    {
        throw new \Exception('Command "'.$console->getExecuteCommand().'" is not callable.');
    }

    public function run()
    {
        foreach( $this->defaultCommands as $command ) {
            $this->setExecuteCommand( $command );
            call_user_func_array( $this->commands[$command], array($this, $this->dispatcher) );
            $this->clearExecute();
        }
    }

    protected function setExecuteCommand( $command )
    {
        $this->executeCommand = $command;
        return $this;
    }

    public function getExecuteCommand()
    {
        return $this->executeCommand;
    }

    protected function clearExecute()
    {
        $this->executeCommand = null;
        return $this;
    }

    public function setEventDispatcher( EventDispatcher $dispatcher )
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    public function addCommands( Array $commands )
    {
        foreach( $commands as $name => $callable ) {
            $this->addCommand( $name, $callable );
        }

        return $this;
    }

    public function addCommand( $name, $callable )
    {
        if( is_callable( $callable ) ) {
            $this->commands[$name] = $callable;
            return true;
        } else {
            throw new \Exception('Command "'.$name.'" is not callable.');
        }
    }

    public function parseArgs ( $args )
    {
        array_shift( $args );

        while ( $arg = array_shift($args) ) {
            // Is it a command? (prefixed with --)
            if ( substr( $arg, 0, 2 ) === '--' ) {

                $value = "";
                $com = substr( $arg, 2 );

                // is it the syntax '--option=argument'?
                if (strpos($com,'=')) {
                    list($com,$value) = split("=",$com,2);
                } elseif (strpos($args[0],'-') !== 0) { // is the option not followed by another option but by arguments
                    while (strpos($args[0],'-') !== 0) {
                        $value .= array_shift($args).' ';
                    }

                    $value = rtrim($value,' ');
                }

                $this->options[$com] = !empty($value) ? $value : true;
                continue;
            }

            // Is it a flag or a serial of flags? (prefixed with -)
            if ( substr( $arg, 0, 1 ) === '-' ) {
                for ($i = 1; isset($arg[$i]) ; $i++) {
                    $this->flags[$arg[$i]] = true;
                }

                continue;
            }

            // Is it a command? (syntax like console:command:echo)
            if (strpos($arg,':')) {
                $this->commands[$arg] = array($this, 'notCallable');
                $this->defaultCommands[] = $arg;
                continue;
            }

            // finally, it is not option, nor flag, nor argument
            $this->arguments[] = $arg;
            continue;
        }

        return $this;
    }
}

class Test
{
    protected $name = 'Test Object';

    public function getName()
    {
        return $this->name;
    }
}

final class TestEvents
{
    const EVENT1 = 'test.event1';
    const EVENT2 = 'test.event2';
}

class TestEvent extends Event
{
    protected $test;

    public function __construct( Test $test )
    {
        $this->test = $test;
    }

    public function getTest()
    {
        return $this->test;
    }
}

class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            TestEvents::EVENT1 => array(
                array('preEvent1', 10),
                array('postEvent1', 0),
            ),
            TestEvents::EVENT2 => array(
                array('preEvent2', 10),
                array('postEvent2', 0),
            ),
        );
    }

    public function preEvent1( TestEvent $event )
    {
        echo "dispatch event \"".TestEvents::EVENT1."\" subscriber preEvent1, get test obj name: {$event->getTest()->getName()}\n";
    }

    public function postEvent1( TestEvent $event )
    {
        echo "dispatch event \"".TestEvents::EVENT1."\" subscriber postEvent1, get test obj name: {$event->getTest()->getName()}\n";
    }

    public function preEvent2( TestEvent $event )
    {
        echo "dispatch event \"".TestEvents::EVENT2."\" subscriber preEvent2, get test obj name: {$event->getTest()->getName()}\n";

        echo "dispatch event \"".TestEvents::EVENT2."\" subscriber preEvent2 stop the event.\n";
        $event->stopPropagation();
    }

    public function postEvent2( TestEvent $event )
    {
        echo "dispatch event \"".TestEvents::EVENT2."\" subscriber postEvent2, get test obj name: {$event->getTest()->getName()}\n";
    }
}

$dispatcher = new EventDispatcher();

// listen test.event1
$dispatcher->addListener( TestEvents::EVENT1, function( TestEvent $event ) {
    echo "dispatch event \"".TestEvents::EVENT1."\" listener, get test obj name: {$event->getTest()->getName()}\n";
} );

// listen test.event2
$dispatcher->addListener( TestEvents::EVENT2, function( TestEvent $event ) {
    echo "dispatch event \"".TestEvents::EVENT2."\" listener, get test obj name: {$event->getTest()->getName()}\n";
} );

// test subscriber
$dispatcher->addSubscriber( new TestSubscriber() );

$console = new Console( $argv );

$console->setEventDispatcher( $dispatcher );

$console->addCommand(
    'test:command1',
    function( Console $console, EventDispatcher $dispatcher )
    {
        $test = new Test();

        echo "{$test->getName()}\n";

        $testevent = new TestEvent($test);

        echo 'dispatch event: '.TestEvents::EVENT1."\n";

        if( $dispatcher->dispatch( TestEvents::EVENT1, $testevent )->isPropagationStopped() ) {
            echo "dispatcher stopped.\n";
        }
    }
);

$console->addCommand(
    'test:command2',
    function( Console $console, EventDispatcher $dispatcher )
    {
        $test = new Test();

        echo "{$test->getName()}\n";

        $testevent = new TestEvent($test);

        echo 'dispatch event: '.TestEvents::EVENT2."\n";

        if( $dispatcher->dispatch( TestEvents::EVENT2, $testevent )->isPropagationStopped() ) {
            echo "dispatcher stopped.\n";
        }
    }
);

$console->run();
