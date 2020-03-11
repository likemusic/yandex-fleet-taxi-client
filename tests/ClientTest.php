<?php
declare(strict_types=1);

namespace Likemusic\YandexFleetTaxiClient\Tests;

use Http\Client\Curl\Client as CurlClient;
use Http\Client\Exception as HttpClientException;
use Http\Discovery\Psr17FactoryDiscovery;
use Likemusic\YandexFleetTaxiClient\Client;
use Likemusic\YandexFleetTaxiClient\Contracts\LanguageInterface;
use Likemusic\YandexFleetTaxiClient\Exception as ClientException;
use Likemusic\YandexFleetTaxiClient\PageParser\FleetTaxiYandexRu\Index as DashboardPageParser;
use Likemusic\YandexFleetTaxiClient\PageParser\PassportYandexRu\Auth\Welcome as WelcomePageParser;
use Likemusic\YandexFleetTaxiClient\Tests\PageParser\FleetTaxiYandexRu\IndexTest;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    const CONFIG_FILENAME = 'tests/ClientTest.json';
    const EXPECTED_DATA_FILENAME = 'tests/ClientTest.Expected.json';

    /**
     * @return Client
     * @throws ClientException
     * @throws HttpClientException
     * @doesNotPerformAssertions
     * @group get
     */
    public function testLogin()
    {
        $testConfig = $this->getTestConfig();
        $curlOptions = $this->getCurlOptions($testConfig);

        $httpClient = new CurlClient(null, null, $curlOptions);
        $requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $welcomePageParser = new WelcomePageParser();
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        $dashboardPageParser = new DashboardPageParser();

        $client = new Client(
            $httpClient,
            $requestFactory,
            $streamFactory,
            $welcomePageParser,
            $dashboardPageParser
        );

        $login = $testConfig['login'];
        $password = $testConfig['password'];

        $client->login($login, $password);

        return $client;
    }

    /**
     * @return array
     */
    private function getTestConfig()
    {
        $configJson = file_get_contents(self::CONFIG_FILENAME);

        return json_decode($configJson, true);
    }

    /**
     * @param array $testConfig
     * @return array
     */
    private function getCurlOptions(array $testConfig)
    {
        $configCurlOptions = $testConfig['curl_options'];

        return [
            CURLOPT_PROXY => $configCurlOptions['proxy'],
            CURLOPT_SSL_VERIFYHOST => $configCurlOptions['verifyhost'],
            CURLOPT_SSL_VERIFYPEER => $configCurlOptions['verifypeer'],
        ];
    }

    /**
     * @param Client $client
     * @return Client
     * @throws HttpClientException
     * @throws ClientException
     * @depends testLogin
     * @group get
     */
    public function testGetDashboardPageData(Client $client)
    {
        $dashboardPageData = $client->getDashboardPageData();
        $expectedDashboardDataLandDefault = $this->getExpectedDashboardDataLandDefault();
        $this->assertEquals($expectedDashboardDataLandDefault, $dashboardPageData);

        return $client;
    }

    /**
     * @return array
     */
    private function getExpectedDashboardDataLandDefault()
    {
        $testConfig = $this->getExpectedData();

        return $testConfig['dashboard']['lang_default'];
    }

    /**
     * @return array
     */
    private function getExpectedData()
    {
        $configJson = file_get_contents(self::EXPECTED_DATA_FILENAME);

        return json_decode($configJson, true);
    }

    /**
     * @param Client $client
     * @return Client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testGetDashboardPageData
     * @group get
     */
    public function testChangeLocale(Client $client): Client
    {
        $dashboardPageData = $client->changeLanguage(LanguageInterface::RUSSIAN);
        $expectedDashboardData = $this->getExpectedDashboardDataLandRussian();
        $this->assertEquals($expectedDashboardData, $dashboardPageData);

        return $client;
    }

    /**
     * @return array
     */
    private function getExpectedDashboardDataLandRussian()
    {
        $testConfig = $this->getTestConfig();

        return $testConfig['dashboard']['lang_russian'];
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     * @group get
     */
    public function testGetDrivers(Client $client)
    {
        $parkId = $this->getTestParkId();
        $driversListData = $client->getDrivers($parkId);

        $this->assertArrayHasKey('status', $driversListData);
        $this->assertEquals(200, $driversListData['status']);

        $this->assertArrayHasKey('success', $driversListData);
        $this->assertTrue($driversListData['success']);

        $this->assertArrayHasKey('data', $driversListData);
        $this->assertIsArray($driversListData['data']);
        $data = $driversListData['data'];
        $this->assertArrayHasKey('driver_profiles', $data);
        $this->assertArrayHasKey('aggregate', $data);

        $this->assertArrayHasKey('total', $driversListData);
        $this->assertIsInt($driversListData['total']);

        $this->assertArrayHasKey('link_drivers_and_orders', $driversListData);
        $this->assertArrayHasKey('show', $driversListData);
    }

    /**
     * @return string
     */
    private function getTestParkId()
    {
        $testConfig = $this->getTestConfig();

        return $testConfig['park_id'];
    }


    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     */
    public function testCreateDriver(Client $client)
    {
        $parkId = IndexTest::PARK_ID;
        $driverPostData = $this->getTestDriverPostData();

        $driverId = $client->createDriver($parkId, $driverPostData);
        $this->assertIsString($driverId);
    }

    private function getTestDriverPostData()
    {
        $driverPostData = FixtureInterface::TEST_DRIVER_DATA;

        $driverPostData['driver_profile']['driver_license']['number'] = $this->generateDriverLicenceNumber();
        $driverPostData['driver_profile']['phones'] = [$this->generatePhoneNumber()];
        $driverPostData['driver_profile']['hire_date'] = date('Y-m-d');

        return $driverPostData;
    }

    private function generateDriverLicenceNumber()
    {
        return $this->generateNumbersString(10);
    }

    private function generateNumbersString($size)
    {
        $ret = '';

        for ($i = 0; $i < $size; $i++) {
            $ret .= rand(0, 9);
        }

        return $ret;
    }

    private function generatePhoneNumber()
    {
        $numbers = $this->generateNumbersString(12);

        return '+' . $numbers;
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     * @group get
     */
    public function testGetVehiclesCardData(Client $client)
    {
        $parkId = $this->getTestParkId();
        $vehiclesCardData = $client->getVehiclesCardData($parkId);
        $this->assertIsArray($vehiclesCardData);
        $this->validateJsonResponseData($vehiclesCardData);

        $expectedVehiclesCardData = $this->getExpectedVehiclesCardData();
        $this->assertEquals($expectedVehiclesCardData, $vehiclesCardData);
    }

    /**
     * @return array
     */
    private function getExpectedVehiclesCardData()
    {
        $expectedData = $this->getExpectedData();

        return $expectedData['vehicles_card_data'];
    }

    private function validateJsonResponseData(array $data)
    {
        $this->assertEquals(200, $data['status']);
        $this->assertTrue($data['success']);
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     * @group get
     */
    public function testGetVehiclesCardModels(Client $client)
    {
        $brandName = $this->getConfigBrandName();
        $data = $client->getVehiclesCardModels($brandName);
        $this->assertIsArray($data);
        $this->validateJsonResponseData($data);

        $expectedVehiclesCardModels = $this->getExpectedVehiclesCardModels();
        $this->assertEquals($expectedVehiclesCardModels, $data);
    }

    /**
     * @return array
     */
    private function getExpectedVehiclesCardModels()
    {
        $expectedData = $this->getExpectedData();

        return $expectedData['vehicles_card_models'];
    }

    /**
     * @return string
     */
    private function getConfigBrandName()
    {
        $testConfig = $this->getTestConfig();

        return $testConfig['brand_name'];
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     */
    public function testStoreVehicles(Client $client)
    {
        $vehiclePostData = [
            'status' => 'working',
            'brand' => 'Alfa Romeo',
            'model' => '105/115',
            'color' => 'Белый',
            'year' => 1996,
            'number' => '1111112',
            'callsign' => 'тест',
            'vin' => '1C3EL46U91N594161',
            'registration_cert' => '1111111',
            'booster_count' => 2,
            'categories' =>
                [
                    0 => 'minivan',
                ],
            'carrier_permit_owner_id' => NULL,
            'transmission' => 'unknown',
            'rental' => false,
            'chairs' =>
                [
                    0 =>
                        [
                            'brand' => 'Еду-еду',
                            'categories' =>
                                [
                                    0 => 'Category2',
                                ],
                            'isofix' => true,
                        ],
                ],
            'permit' => '777777',
            'tariffs' =>
                [
                    0 => 'Эконом',
                ],
            'cargo_loaders' => 1,
            'carrying_capacity' => 300,
            'chassis' => '234',
            'park_id' => '8d40b7c41af544afa0499b9d0bdf2430',
            'amenities' =>
                [
                    0 => 'conditioner',
                    1 => 'child_seat',
                    2 => 'delivery',
                    3 => 'smoking',
                    4 => 'woman_driver',
                    5 => 'sticker',
                    6 => 'charge',
                ],
            'cargo_hold_dimensions' =>
                [
                    'length' => 150,
                    'width' => 100,
                    'height' => 50,
                ],
            'log_time' => 350,
        ];

        $data = $client->storeVehicles($vehiclePostData);
        $this->assertIsArray($data);
        $this->validateJsonResponseData($data);
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     */
    public function testBindDriverWithCar(Client $client)
    {
        $parkId = IndexTest::PARK_ID;
        $testConfig = $this->getTestConfig();
        $driverId = $this->getTestDriverId($testConfig);
        $carId = $this->getTestCarId($testConfig);
        $data = $client->bindDriverWithCar($parkId, $driverId, $carId);
        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
    }

    /**
     * @param array $testConfig
     * @return string
     */
    private function getTestDriverId(array $testConfig)
    {
        return $testConfig['driver_id'];
    }

    private function getTestCarId(array $testConfig)
    {
        return $testConfig['car_id'];
    }

    /**
     * @param Client $client
     * @throws ClientException
     * @throws HttpClientException
     * @depends testChangeLocale
     * @group get
     */
    public function testGetDriversCardData(Client $client)
    {
        $parkId = $this->getTestParkId();
        $data = $client->getDriversCardData($parkId);
        $this->assertIsArray($data);
        $this->validateJsonResponseData($data);
        $expectedDriversCardData = $this->getExpectedDriversCardData();

        $this->assertEquals($expectedDriversCardData, $data);
    }

    private function getExpectedDriversCardData()
    {
        $expectedData = $this->getExpectedData();

        return $expectedData['drivers_card_data'];
    }
}
