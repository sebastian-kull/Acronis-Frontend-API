<?php

namespace App\Controllers;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

enum App
{
    case CyberProtection;

    public function get(): string
    {
        return match ($this) {
            App::CyberProtection => 'Cyber Protection',
        };
    }
}
enum Method
{
    case POST;
    case DELETE;

    public function get(): string
    {
        return match ($this) {
            Method::POST => 'POST',
            Method::DELETE => 'DELETE',
        };
    }
}
enum Quotas
{
    case WORKSTATIONS;
    case STORAGE;

    public function get(): string
    {
        return match ($this) {
            Quotas::WORKSTATIONS => 'workstations',
            Quotas::STORAGE => 'storage',
        };
    }
}

class Home extends BaseController
{
    private CURLRequest $client;
    private string $api_base_url = 'https://portal.backup.ch/api/2/';
    private string $client_id = '8189e982-9ad2-4cf5-bb2c-0bd10bf1869c';
    private string $parent_id = '489a88b1-ac84-423d-9e80-d8c570062e53';
    private string $secret = 'xjjwmos2xtbzntkp223kvomiciifkrbkgoddufc7ebgfgy4pizpe';
    private string $access_token;


    public function __construct()
    {
        $this->client = Services::curlrequest(["http_errors" => false, "baseURI" => $this->api_base_url]);

        $response = $this->client->request("POST", "idp/token",
            [
                "auth" => [$this->client_id, $this->secret],
                "form_params" => ["grant_type" => "client_credentials"]
            ]);
        $object = json_decode($response->getBody());
        $this->client->setHeader("Authorization", "Bearer " . $object->access_token);
        $this->access_token = $object->access_token;
    }

    /**
     * main function
     * @return void
     */


    public
    function index(): void
    {
//        $tenantName = $this->"testName";
//        $userName = $this->"testName";
        $tenantName = $this->generateRandomString();
        $userName = $this->generateRandomString();
        $this->creatingATenant($tenantName);
        $tenant_id = $this->getTenantId($tenantName);
        $this->creatingUseraccount($tenant_id, $userName);
        $userId = $this->getUserId($userName, $tenant_id);
        $this->activateUserAccount($userId);
        $application_id = $this->getApplicationId(App::CyberProtection->get());
        $this->enableApplication(Method::POST->get(), $application_id, $tenant_id);

        $quotas = [
            [
                "name" => Quotas::STORAGE->get(),
                "value" => 100,
                "activate" => 1,
                "applicationId" => "72363496-294e-48be-995b-0973892166d3"
            ],
            [
                "name" => Quotas::WORKSTATIONS->get(),
                "value" => 100,
                "activate" => 1,
                "applicationId" => null

            ],
        ];
        $this->activateApplication($tenant_id, $quotas);
        $this->setQuotas($tenant_id, $quotas);

        $this->modifyCurrentRoles($tenant_id, $userId);
        $this->checkCurrentRoles($userId);
    }

    /**
     * creating an acronis tenant on backup.ch portal
     * @param $name
     * @return void
     */
    public
    function creatingATenant($name): void
    {
        $tenant = array(
            "name" => $name,
            "kind" => "customer",
            "parent_id" => $this->parent_id,
        );

        $response = $this->client->request("POST", "tenants", ["json" => $tenant]);
        print_r("create tenant: " . $response->getStatusCode() . "<br>");
    }

    /**
     * get tenant id by name
     * @param string $name
     * @return string
     */
    public
    function getTenantId(string $name): string
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", "tenants", ["query" => ["subtree_root_id" => $this->parent_id]]);
        $object = json_decode($response->getBody());
        $id = null;
        foreach ($object->items as $o) {
            if ($o->name == $name) {
                $id = $o->id;
                print_r("tenant ID: " . $id . "<br>");
                break;
            }
        }
        return $id;
    }

    /**
     * creating an acronis user account on backup.ch portal
     * @param string $tenant_id
     * @param string $username
     * @return void
     */
    public
    function creatingUserAccount(string $tenant_id, string $username): void
    {
//        check if name available
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", 'users/check_login', ['query' => ['username' => $username]]);
        print_r("username available: " . $response->getStatusCode() . "<br>");

//       if name available
        if ($response->getStatusCode() == 204) {
            $userdata = [
                "tenant_id" => $tenant_id,
                "login" => $username,
                "contact" => [
                    "email" => "uniqueName@gmail.com",
                    "firstname" => "$username",
                    "lastname" => "$username"
                ]
            ];
            $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
            $response = $this->client->request("POST", "users", ["json" => $userdata]);
            print_r("create user account: " . $response->getStatusCode() . "<br>");
        }
    }

    /**
     * activate user Account via mail. Sends an activation mail to the user.
     * @param string $userId
     * @return void
     */
    public
    function activateUserAccount(string $userId): void
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("POST", "users/" . $userId . "/send-activation-email", ["json" => ""]);
        print_r("Activate User via Mail: " . $response->getStatusCode() . "<br>");
    }

    /**
     * get user id by name
     * @param string $name
     * @param string $tenant_id
     * @return string
     */
    public
    function getUserId(string $name, string $tenant_id): string
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", "users", ["query" => ["subtree_root_tenant_id" => $tenant_id]]);
        $object = json_decode($response->getBody());
        $id = "";
        foreach ($object->items as $o) {
            if ($o->login == $name) {
                $id = $o->id;
                print_r("user ID: " . $id . "<br>");
                break;
            }
        }
        return $id;
    }

    /**
     * enable or disable an application for a tenant
     * @param string $method "POST" or "GET"
     * @param string $application_id
     * @param string $tenant_id
     * @return void
     */
    public
    function enableApplication(string $method, string $application_id, string $tenant_id): void
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request($method, "applications/" . $application_id . "/bindings/tenants/" . $tenant_id);
        print_r($response->getBody());
        print_r("enable/disable application: " . $response->getStatusCode() . "<br>");
    }

    /**
     * activate an application for a tenant
     * @param $tenant_id
     * @param $quotas
     * @return void
     */
    public
    function activateApplication($tenant_id, $quotas): void
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $result = $this->client->request("GET", "tenants/" . $tenant_id . "/offering_items", ["query" => ["edition" => "pck_per_workload"]]);
        $object = json_decode($result->getBody());

        foreach ($object->items as $o) {
            foreach ($quotas as $quota) {
                if ($quota["applicationId"] != null) {
                    if ($o->usage_name == $quota["name"] && $o->infra_id == $quota["applicationId"]) {
                        $o->status = $quota["activate"];
                    }
                } elseif ($o->usage_name == $quota["name"]) {
                    $o->status = $quota["activate"];
                }
            }
        }
        $updated_offering_items = ["offering_items" => $object->items];
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $result = $this->client->request("PUT", "tenants/" . $tenant_id . "/offering_items", ["json" => $updated_offering_items]);
        print_r("activate quotas: " . $result->getStatusCode() . "<br>");
    }

    /**
     * set quota of application
     * @param $tenant_id
     * @param $quotas
     * @return void
     */
    public
    function setQuotas($tenant_id, $quotas): void
    {


        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $result = $this->client->request("GET", "tenants/" . $tenant_id . "/offering_items", ["query" => ["edition" => "pck_per_workload"]]);
        $object = json_decode($result->getBody());

        foreach ($object->items as $o) {
            foreach ($quotas as $quota) {
                if ($quota["applicationId"] != null) {
                    if ($o->usage_name == $quota["name"] && $o->infra_id == $quota["applicationId"]) {
                        if ($o->status == 1) {
                            $o->quota->value = pow(1024, 3) * $quota["value"];
                        }
                    }
                } elseif ($o->usage_name == $quota["name"]) {
                    if ($o->status == 1) {
                        $o->quota->value = pow(1024, 3) * $quota["value"];
                    }
                }
            }
        }
        $updated_offering_items = ["offering_items" => $object->items];
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $result = $this->client->request("PUT", "tenants/" . $tenant_id . "/offering_items", ["json" => $updated_offering_items]);
        print_r("set quotas: " . $result->getStatusCode() . "<br>");
    }

    /**
     * get id of an application by name
     * @param string $name
     * @return string
     */
    public
    function getApplicationId(string $name): string
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", "applications");
        $object = json_decode($response->getBody())->items;
        $id = "";
        foreach ($object as $o) {
            if ($o->name == $name) {
                $id = $o->id;
                print_r("application ID: " . $id . "<br>");
                break;
            }
        }
        return $id;
    }

    /**
     * get current roles of a user
     * @param $user_id
     * @return void
     */
    public
    function checkCurrentRoles($user_id): void
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", "users/" . $user_id . "/access_policies");
        $roles = json_decode($response->getBody());
        print_r("roles: <br>");
        foreach ($roles->items as $role) {
            print_r("--- " . $role->role_id . "<br>");
        }
    }

    /**
     * modify roles of a user
     * @param $tenant_id
     * @param $user_id
     * @return void
     */
    public
    function modifyCurrentRoles($tenant_id, $user_id): void
    {
        $policies_object = [
            "items" => [
                [
                    "id" => "00000000-0000-0000-0000-000000000000",
                    "issuer_id" => "00000000-0000-0000-0000-000000000000",
                    "trustee_id" => $user_id,
                    "trustee_type" => "user",
                    "tenant_id" => $tenant_id,
                    "role_id" => "company_admin",
                    "version" => 0
                ]
            ]
        ];
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("PUT", "users/" . $user_id . "/access_policies", ["json" => $policies_object]);
        print_r("modify role: " . $response->getStatusCode() . "<br>");
    }

    /**
     * creating a unique test name
     * @param int $length
     * @return string
     */
    public
    function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}

