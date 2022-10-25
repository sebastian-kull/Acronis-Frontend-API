<?php

namespace App\Controllers;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class Home extends BaseController
{
    private CURLRequest $client;
    private string $api_base_url = 'https://portal.backup.ch/api/2/';
    private string $client_id = '8189e982-9ad2-4cf5-bb2c-0bd10bf1869c';
    private string $parent_id = '489a88b1-ac84-423d-9e80-d8c570062e53';
    private string $secret = 'xjjwmos2xtbzntkp223kvomiciifkrbkgoddufc7ebgfgy4pizpe';
    private string $cyber_protection = 'Cyber Protection';
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

    public function index()
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
        $application_id = $this->getApplicationId($this->cyber_protection);
        $this->enableApplication("POST", $application_id, $tenant_id);

        $quotas = [
            [
                "name" => "storage",
                "value" => 100,
                "activate" => 1,
                "applicationId" => "72363496-294e-48be-995b-0973892166d3"
            ],
            [
                "name" => "workstations",
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

    public function creatingUserAccount(string $tenant_id, string $username)
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

    public function activateUserAccount(string $userId)
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("POST", "users/" . $userId . "/send-activation-email", ["json" => ""]);
        print_r("Activate User via Mail: " . $response->getStatusCode() . "<br>");
    }

    public function activateApplication($tenant_id, $quotas)
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

    public function setQuotas($tenant_id, $quotas)
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

    public function getTenantId(string $name): string
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

    public function getUserId(string $name, string $tenant_id): string
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

    public function enableApplication(string $method, string $application_id, string $tenant_id)
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request($method, "applications/" . $application_id . "/bindings/tenants/" . $tenant_id);
        print_r($response->getBody());
        print_r("enable/disable application: " . $response->getStatusCode() . "<br>");
    }

    public function getApplicationId(string $name): string
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

    public function creatingATenant($name)
    {
        $tenant = array(
            "name" => $name,
            "kind" => "customer",
            "parent_id" => $this->parent_id,
        );

        $response = $this->client->request("POST", "tenants", ["json" => $tenant]);
        print_r("create tenant: " . $response->getStatusCode() . "<br>");
    }

    public function generateRandomString($length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function checkCurrentRoles($user_id)
    {
        $this->client->setHeader("Authorization", "Bearer " . $this->access_token);
        $response = $this->client->request("GET", "users/" . $user_id . "/access_policies");
        $roles = json_decode($response->getBody());
        print_r("roles: <br>");
        foreach ($roles->items as $role) {
            print_r("--- " . $role->role_id . "<br>");
        }
    }

    public function modifyCurrentRoles($tenant_id, $user_id)
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
}
