<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'TestHelper.php';

require_once 'PHPUnit/Framework.php';
require_once 'Shanty/Mongo/Connection/Group.php';
require_once 'Shanty/Mongo/Connection/Stack.php';
 
class Shanty_Mongo_Connection_GroupTest extends PHPUnit_Framework_TestCase
{
	protected $_group;
	
	public function setUp()
	{
		$this->_group = new Shanty_Mongo_Connection_Group();
	}
	
	public function testAddConnectionsSingleServer()
	{
		$connections = array('host' => 'localhost');
		$this->_group->addConnections($connections);
		$this->assertEquals(1, count($this->_group->getMasters()));
		$this->assertEquals(0, count($this->_group->getSlaves()));
		
		$writeConnection = $this->_group->getWriteConnection();
		$this->assertType(PHPUnit_Framework_Constraint_IsType::TYPE_OBJECT, $writeConnection);
		
		$writeConnectionInfo = $writeConnection->getConnectionInfo();
		$this->assertEquals('mongodb://localhost:27017', $writeConnectionInfo['connectionString']);
		
		// Make sure read and write connections are the same
		$this->assertEquals($writeConnection, $this->_group->getReadConnection());
	}
	
	public function testAddConnectionsSingleMasterSingleSlave()
	{
		$connections = array(
			'master' => array('host' => 'localhost'),
			'slave' => array('host' => '127.0.0.1'),
		);
		
		$this->_group->addConnections($connections);
		$this->assertEquals(1, count($this->_group->getMasters()));
		$this->assertEquals(1, count($this->_group->getSlaves()));
		
		$masters = $this->_group->getMasters();
		$slaves = $this->_group->getSlaves();
		
		$masterInfo = $masters[0]->getConnectionInfo();
		$this->assertEquals('mongodb://localhost:27017', $masterInfo['connectionString']);
		
		$slave1Info = $slaves[0]->getConnectionInfo();
		$this->assertEquals('mongodb://127.0.0.1:27017', $slave1Info['connectionString']);
	}
	
	public function testAddConnectionsMultiMasterMultiSlave()
	{
		$connections = array(
			'masters' => array(
				0 => array('host' => '127.0.0.1'),
				1 => array('host' => 'localhost')
			),
			'slaves' => array(
				0 => array('host' => '127.0.0.1'),
				1 => array('host' => 'localhost')
			)
		);
		
		$this->_group->addConnections($connections);
		$this->assertEquals(2, count($this->_group->getMasters()));
		$this->assertEquals(2, count($this->_group->getSlaves()));
		
		$masters = $this->_group->getMasters();
		$slaves = $this->_group->getSlaves();
		
		$master1Info = $masters[0]->getConnectionInfo();
		$this->assertEquals('mongodb://127.0.0.1:27017', $master1Info['connectionString']);
		
		$master2Info = $masters[1]->getConnectionInfo();
		$this->assertEquals('mongodb://localhost:27017', $master2Info['connectionString']);
		
		$slave1Info = $slaves[0]->getConnectionInfo();
		$this->assertEquals('mongodb://127.0.0.1:27017', $slave1Info['connectionString']);
		
		$slave2Info = $slaves[1]->getConnectionInfo();
		$this->assertEquals('mongodb://localhost:27017', $slave2Info['connectionString']);
	}
	
	public function testAddAndGetMasters()
	{
		$this->assertEquals(0, count($this->_group->getMasters()));
		$this->_group->addMaster($this->getMock('Shanty_Mongo_Connection'));
		$this->_group->addMaster($this->getMock('Shanty_Mongo_Connection'));
		$this->assertEquals(2, count($this->_group->getMasters()));
	}
	
	public function testAddAndGetSlaves()
	{
		$this->assertEquals(0, count($this->_group->getSlaves()));
		$this->_group->addSlave($this->getMock('Shanty_Mongo_Connection'));
		$this->_group->addSlave($this->getMock('Shanty_Mongo_Connection'));
		$this->assertEquals(2, count($this->_group->getSlaves()));
	}
	
	public function testGetWriteConnection()
	{
		$master = $this->getMock('Shanty_Mongo_Connection', array('connect'));
		$master->expects($this->once())->method('connect');
		$this->_group->addMaster($master);
		$this->assertEquals($master, $this->_group->getWriteConnection());
	}
	
	public function testGetReadConnection()
	{
		// Test no slaves, only master
		$master = $this->getMock('Shanty_Mongo_Connection', array('connect'));
		$master->expects($this->once())->method('connect');
		$this->_group->addMaster($master);
		
		$this->assertEquals($master, $this->_group->getReadConnection());
		
		// Test slaves plus master
		$slave = $this->getMock('Shanty_Mongo_Connection', array('connect'));
		$slave->expects($this->once())->method('connect');
		$this->_group->addSlave($slave);
		
		$this->assertEquals($slave, $this->_group->getReadConnection());
	}
	
	public function testFormatConnectionString()
	{
		$this->assertEquals('mongodb://127.0.0.1:27017', $this->_group->formatConnectionString());
		
		$options = array('host' => 'mongodb.local');
		$this->assertEquals("mongodb://{$options['host']}:27017", $this->_group->formatConnectionString($options));
		
		$options = array(
			'replica_pair' => array(
				array('host' => 'mongodb1.local'),
				array('host' => 'mongodb2.local'),
			)
		);
		$this->assertEquals("mongodb://{$options['replica_pair'][0]['host']}:27017,{$options['replica_pair'][1]['host']}:27017", $this->_group->formatConnectionString($options));
	}
	
	public function testFormatHostString()
	{
		 $this->assertEquals('127.0.0.1:27017', $this->_group->formatHostString());
		 
		 $options = array('host' => 'mongodb.local');
		 $this->assertEquals("{$options['host']}:27017", $this->_group->formatHostString($options));
		 
		 $options = array('port' => '27018');
		 $this->assertEquals("127.0.0.1:{$options['port']}", $this->_group->formatHostString($options));
		 
		 $options = array('username' => 'jerry', 'password' => 'springer');
		 $this->assertEquals("{$options['username']}:{$options['password']}@127.0.0.1:27017", $this->_group->formatHostString($options));
	}
	
	
}