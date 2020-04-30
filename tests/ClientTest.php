<?php
declare(strict_types=1);

namespace YandexFleetTaxi\Client\Tests;

use Http\Client\Curl\Client as CurlClient;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use YandexFleetTaxi\Client\Client;
use YandexFleetTaxi\Client\Contracts\LanguageInterface;
use YandexFleetTaxi\Client\Exception as ClientException;
use YandexFleetTaxi\Client\HttpJsonResponseException;
use YandexFleetTaxi\Client\HttpResponseException;
use YandexFleetTaxi\Client\PageParser\FleetTaxiYandexRu\Index as DashboardPageParser;
use YandexFleetTaxi\Client\PageParser\PassportYandexRu\Auth\Welcome as WelcomePageParser;

final class ClientTest extends TestCase
{
    const FILENAME_CONFIG = 'tests/ClientTest.json';
    const FILENAME_CONFIG_COMMON = 'tests/ClientTest.Common.json';
    const FILENAME_EXPECTED_DATA_COMMON = 'tests/ClientTest.Expected.Common.json';
    const FILENAME_TEMPLATE_EXPECTED_DATA_PARK = 'tests/ClientTest.Expected.{parkId}.json';
    const FILENAME_POST_DATA_DRIVER_TEMPLATE = 'tests/Textures/PostData/Driver.php';
    const FILENAME_POST_DATA_CAR_TEMPLATE = 'tests/Textures/PostData/Car.php';

    /**
     * @return Client
     * @throws ClientException
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @doesNotPerformAssertions
     * @group get
     */
    public function testLogin()
    {
        $curlOptions = $this->getCurlOptions();
        $httpClient = new CurlClient(null, null, $curlOptions);
        $welcomePageParser = new WelcomePageParser();
        $dashboardPageParser = new DashboardPageParser();

        $client = new Client(
            $welcomePageParser,
            $dashboardPageParser,
            $httpClient
        );

        $testConfig = $this->getTestConfig();
        $login = $testConfig['login'];
        $password = $testConfig['password'];

        $client->login($login, $password);

        return $client;
    }

    /**
     * @return array
     */
    private function getCurlOptions()
    {
        $testConfigCommon = $this->getTestConfigCommon();

        $configCurlOptions = $testConfigCommon['curl_options'];

        return [
            CURLOPT_PROXY => $configCurlOptions['proxy'],
            CURLOPT_SSL_VERIFYHOST => $configCurlOptions['verifyhost'],
            CURLOPT_SSL_VERIFYPEER => $configCurlOptions['verifypeer'],
            CURLOPT_FOLLOWLOCATION => $configCurlOptions['followlocation'],
        ];
    }

    /**
     * @return array
     */
    private function getTestConfigCommon()
    {
        $configJson = file_get_contents(self::FILENAME_CONFIG_COMMON);

        return json_decode($configJson, true);
    }

    /**
     * @return array
     */
    private function getTestConfig()
    {
        $configJson = file_get_contents(self::FILENAME_CONFIG);

        return json_decode($configJson, true);
    }

    /**
     * @param Client $client
     * @return Client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testLogin
     * @group get
     */
    public function testGetDashboardPageData(Client $client)
    {
        $data = $client->getDashboardPageData();
        $expectedDashboardDataLandDefault = $this->getExpectedDashboardDataLandDefault();
        $this->assertEquals($expectedDashboardDataLandDefault, $data);

        return $client;
    }

    /**
     * @return array
     */
    private function getExpectedDashboardDataLandDefault()
    {
        $testConfig = $this->getExpectedDataPark();

        return $testConfig['dashboard']['lang_default'];
    }

    /**
     * @return array
     */
    private function getExpectedDataPark()
    {
        $expectedDataParkFilename = $this->getExpectedDataParkFilename();
        $configJson = file_get_contents($expectedDataParkFilename);

        return json_decode($configJson, true);
    }

    private function getExpectedDataParkFilename()
    {
        $parkId = $this->getTestParkId();

        return str_replace('{parkId}', $parkId, self::FILENAME_TEMPLATE_EXPECTED_DATA_PARK);
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
     * @return Client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testGetDashboardPageData
     * @group get
     */
    public function testChangeLocale(Client $client): Client
    {
        $data = $client->changeLanguage(LanguageInterface::RUSSIAN);
        $expectedDashboardData = $this->getExpectedDashboardDataLandRussian();
        $this->assertEquals($expectedDashboardData, $data);

        return $client;
    }

    /**
     * @return array
     */
    private function getExpectedDashboardDataLandRussian()
    {
        $testConfig = $this->getExpectedDataPark();

        return $testConfig['dashboard']['lang_russian'];
    }

    /**
     * @param Client $client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
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
     * @param Client $client
     * @return string
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testChangeLocale
     */
    public function testCreateDriver(Client $client)
    {
        $parkId = $this->getTestParkId();
        $driverPostData = $this->getTestDriverPostData();

        $driverId = $client->createDriver($parkId, $driverPostData);
        $this->assertIsString($driverId);
        $this->assertEquals(32, strlen($driverId));

        return $driverId;
    }

    private function getTestDriverPostData()
    {
        $driverPostData = $this->getDriverPostDataTemplate();

        $driverPostData['driver_profile']['driver_license']['number'] = $this->generateDriverLicenceNumber();
        $driverPostData['driver_profile']['phones'] = [$this->generatePhoneNumber()];
        $driverPostData['driver_profile']['hire_date'] = date('Y-m-d');
        $driverPostData['driver_profile']['work_rule_id'] = $this->getTestWorkRuleId();

        return $driverPostData;
    }

    /**
     * @return array
     */
    private function getDriverPostDataTemplate()
    {
        return include self::FILENAME_POST_DATA_DRIVER_TEMPLATE;
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

    private function getTestWorkRuleId()
    {
        $testConfig = $this->getTestConfig();

        return $testConfig['work_rule_id'];
    }

    /**
     * @param Client $client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testChangeLocale
     * @group get
     */
    public function testGetVehiclesCardData(Client $client)
    {
        $parkId = $this->getTestParkId();
        $data = $client->getVehiclesCardData($parkId);
        $this->assertIsArray($data);
        $this->validateJsonResponseData($data);

        $expectedVehiclesCardData = $this->getExpectedVehiclesCardData();
        $this->assertEquals($expectedVehiclesCardData, $data);
    }

    private function validateJsonResponseData(array $data)
    {
        $this->assertEquals(200, $data['status']);
        $this->assertTrue($data['success']);
    }

    /**
     * @return array
     */
    private function getExpectedVehiclesCardData()
    {
        $expectedData = $this->getExpectedDataPark();

        return $expectedData['vehicles_card_data'];
    }

    /**
     * @param Client $client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
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
     * @return string
     */
    private function getConfigBrandName()
    {
        $testConfig = $this->getTestConfigCommon();

        return $testConfig['brand_name'];
    }

    /**
     * @return array
     */
    private function getExpectedVehiclesCardModels()
    {
        $expectedData = $this->getExpectedDataCommon();

        return $expectedData['vehicles_card_models'];
    }

    private function getExpectedDataCommon()
    {
        $configJson = file_get_contents(self::FILENAME_EXPECTED_DATA_COMMON);

        return json_decode($configJson, true);
    }

    /**
     * @param Client $client
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testChangeLocale
     */
    public function testCreateCar(Client $client)
    {
        $parkId = $this->getTestParkId();
        $carPostData = $this->getCarPostData();
        $data = $client->createCar($parkId, $carPostData);
        $this->assertIsArray($data);
        $this->assertArrayHasKeys([
            'id',
            'park_id',
            'brand',
            'model',
            'color',
            'year',
            'number',
            'callsign',
            'mileage',
            'vin',
            'booster_count',
            'registration_cert',
            'status',
            'transmission',
            'amenities',
            'tariffs'
        ], $data);

        return $data['id'];
    }

    /**
     * @return array
     */
    private function getCarPostData()
    {
        return include self::FILENAME_POST_DATA_CAR_TEMPLATE;
    }

    private function assertArrayHasKeys(array $keys, array $array)
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    /**
     * @param Client $client
     * @param string $driverId
     * @param string $carId
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
     * @depends testChangeLocale
     * @depends testCreateDriver
     * @depends testCreateCar
     */
    public function testBindDriverWithCar(Client $client, string $driverId, string $carId)
    {
        $parkId = $this->getTestParkId();
        $data = $client->bindDriverWithCar($parkId, $driverId, $carId);
        $this->assertIsArray($data);
        $this->assertArrayHasKeys('status', $data);
        $this->assertEquals('success', $data['status']);
    }

    /**
     * @param Client $client
     * @throws ClientExceptionInterface
     * @throws HttpJsonResponseException
     * @throws HttpResponseException
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
        $expectedData = $this->getExpectedDataPark();

        return $expectedData['drivers_card_data'];
    }

    /**
     * @param Client $client
     * @depends testGetDashboardPageData
     * @group post
     */
    public function testGetDriverScoringList(Client $client)
    {
        $parkId = $this->getTestParkId();
        $license = $this->getTestLicense();
        $idempotencyToken = '10ec497e-9b6e-49b8-a209-df3032279b6a';

        $data = $client->getDriverScoringList($parkId, $license, $idempotencyToken);
        $this->assertIsArray($data);
        $expectedDriversCardData = $this->getExpectedDriverScoringList();
        $this->assertEquals($expectedDriversCardData, $data);
    }

    private function getTestLicense(): string
    {
        $testConfig = $this->getTestConfig();

        return $testConfig['license'];
    }

    private function getExpectedDriverScoringList(): array
    {
        $expectedData = $this->getExpectedDataPark();

        return $expectedData['driver_scoring_list'];
    }
}
