<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\XeroTenant\BulkXeroTenantRequest;
use App\Http\Requests\XeroTenant\DestroyXeroTenantRequest;
use App\Http\Requests\XeroTenant\LinkXeroTenantRequest;
use App\Http\Requests\XeroTenant\ShowXeroTenantRequest;
use App\Http\Requests\XeroTenant\UpdateXeroTenantRequest;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\XeroTenant;
use App\Repositories\BaseRepository;
use App\Transformers\XeroTenantTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class XeroTenantController extends BaseController
{
    use MakesHash;

    protected $entity_type = XeroTenant::class;

    protected $entity_transformer = XeroTenantTransformer::class;

    public $base_repo;

    public function __construct(BaseRepository $base_repo)
    {
        parent::__construct();

        $this->base_repo = $base_repo;
    }

    /**
     * Display the specified resource.
     *
     * @param ShowXeroTenantRequest $request
     * @param XeroTenant $xero_tenant
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/xero_tenants/{id}",
     *      operationId="showXeroTenant",
     *      tags={"xero_tenants"},
     *      summary="Shows a XeroTenant",
     *      description="Displays a XeroTenant by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The XeroTenant Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the XeroTenant object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/XeroTenant"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowXeroTenantRequest $request, XeroTenant $xero_tenant)
    {
        return $this->itemResponse($xero_tenant);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateXeroTenantRequest $request
     * @param XeroTenant $xero_tenant
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/xero_tenants/{id}",
     *      operationId="updateXeroTenant",
     *      tags={"xero_tenants"},
     *      summary="Updates a XeroTenant",
     *      description="Handles the updating of a XeroTenant by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The XeroTenant Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the XeroTenant object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/XeroTenant"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateXeroTenantRequest $request, XeroTenant $xero_tenant)
    {
        if ($request->entityIsDeleted($xero_tenant)) {
            return $request->disallowUpdate();
        }

        $xero_tenant->fill($request->all());
        $xero_tenant->save();

        return $this->itemResponse($xero_tenant);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyXeroTenantRequest $request
     * @param XeroTenant $xero_tenant
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/xero_tenants/{id}",
     *      operationId="deleteXeroTenant",
     *      tags={"XeroTenants"},
     *      summary="Deletes a XeroTenant",
     *      description="Handles the deletion of a XeroTenant by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The XeroTenant Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyXeroTenantRequest $request, XeroTenant $xero_tenant)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $this->base_repo->delete($xero_tenant);

        return $this->itemResponse($xero_tenant);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/xero_tenants/bulk",
     *      operationId="bulkXeroTenants",
     *      tags={"xero_tenants"},
     *      summary="Performs bulk actions on an array of XeroTenants",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The XeroTenant User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/XeroTenant"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk(BulkXeroTenantRequest $request)
    {
        $action = $request->input('action');

        $ids = $request->input('ids');

        XeroTenant::withTrashed()
                  ->whereIn('id', $ids)
                  ->where('account_id', auth()->user()->account_id)
                  ->cursor()
                  ->each(function ($xero_tenant, $key) use ($action) {
                        $this->base_repo->{$action}($xero_tenant);
                  });

        return $this->listResponse(XeroTenant::withTrashed()->where('account_id', auth()->user()->account_id)->whereIn('id', $ids));
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/xero_tenants/{xero_tenant}/{company?}/link",
     *      operationId="linkXeroTenant",
     *      tags={"xero_tenants"},
     *      summary="Links a XeroTenant to a company",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="The XeroTenant User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/XeroTenant"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function link(LinkXeroTenantRequest $request, XeroTenant $xero_tenant, ?string $company_key = null)
    {
        $company = false;

        if($company_key){
            $company = Company::where('company_key', $company_key)->where('account_id', $xero_tenant->account_id)->firstOrFail();
            $xero_tenant->company_id = $company->id;
        }
        else
            $xero_tenant->company_id = null;

        $xero_tenant->save();
        
        return $this->itemResponse($xero_tenant);

    }

}
