<?php

namespace SoliDry\Extension;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use League\Fractal\Resource\Collection;
use SoliDry\Helpers\ConfigOptions;
use SoliDry\Blocks\EntitiesTrait;
use SoliDry\Containers\Response;
use Illuminate\Http\Response as IlluminateResponse;
use SoliDry\Types\HTTPMethodsInterface;
use SoliDry\Types\JwtInterface;
use SoliDry\Helpers\Json;
use SoliDry\Types\PhpInterface;

/**
 * Class ApiController
 *
 * @package SoliDry\Extension
 *
 * @property Response response
 */
class ApiController extends Controller implements JSONApiInterface
{
    use BaseRelationsTrait,
        OptionsTrait,
        EntitiesTrait,
        JWTTrait,
        FsmTrait,
        SpellCheckTrait,
        BitMaskTrait,
        CacheTrait;

    // JSON API support enabled by default
    /**
     * @var bool
     */
    protected bool $jsonApi = true;

    /**
     * @var array
     */
    protected array $props = [];

    protected $entity;

    /**
     * @var BaseModel
     */
    protected BaseModel $model;

    /**
     * @var EntitiesTrait
     */
    private EntitiesTrait $modelEntity;

    protected $formRequest;

    /**
     * @var bool
     */
    private bool $relsRemoved    = false;

    /**
     * @var array
     */
    private array $defaultOrderBy = [];

    /**
     * @var ConfigOptions
     */
    protected ConfigOptions $configOptions;

    /**
     * @var CustomSql
     */
    protected CustomSql $customSql;

    /**
     * @var BitMask
     */
    private BitMask $bitMask;

    private $response;

    /**
     * @var array
     */
    private array $jsonApiMethods = [
        JSONApiInterface::URI_METHOD_INDEX,
        JSONApiInterface::URI_METHOD_VIEW,
        JSONApiInterface::URI_METHOD_CREATE,
        JSONApiInterface::URI_METHOD_UPDATE,
        JSONApiInterface::URI_METHOD_DELETE,
        JSONApiInterface::URI_METHOD_RELATIONS,
    ];

    /**
     * BaseControllerTrait constructor.
     *
     * @param Route $route
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \ReflectionException
     */
    public function __construct(Route $route)
    {
        // add relations to json api methods array
        $this->addRelationMethods();
        $actionName   = $route->getActionName();
        $calledMethod = substr($actionName, strpos($actionName, PhpInterface::AT) + 1);
        if ($this->jsonApi === false && in_array($calledMethod, $this->jsonApiMethods)) {
            Json::outputErrors(
                [
                    [
                        JSONApiInterface::ERROR_TITLE  => 'JSON API support disabled',
                        JSONApiInterface::ERROR_DETAIL => 'JSON API method ' . $calledMethod
                            .
                            ' was called. You can`t call this method while JSON API support is disabled.',
                    ],
                ]
            );
        }

        $this->setEntities();
        $this->setDefaults();
        $this->setConfigOptions($calledMethod);
    }

    /**
     * Responds with header of an allowed/available http methods
     * @return mixed
     */
    public function options()
    {
        // this seems like needless params passed by default, but they needed for backward compatibility in Laravel prev versions
        return response('', 200)->withHeaders([
            'Allow'                            => HTTPMethodsInterface::HTTP_METHODS_AVAILABLE,
            JSONApiInterface::CONTENT_TYPE_KEY => JSONApiInterface::HEADER_CONTENT_TYPE_VALUE,
        ]);
    }

    /**
     * GET Output all entries for this Entity with page/limit pagination support
     *
     * @param Request $request
     * @return IlluminateResponse
     * @throws \SoliDry\Exceptions\AttributesException
     */
    public function index(Request $request) : IlluminateResponse
    {
        $meta       = [];
        $sqlOptions = $this->setSqlOptions($request);
        if ($this->isTree === true) {
            $tree = $this->getAllTreeEntities($sqlOptions);
            $meta = [strtolower($this->entity) . PhpInterface::UNDERSCORE . JSONApiInterface::META_TREE => $tree->toArray()];
        }

        $this->setPagination(true);
        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $pages */
        if ($this->configOptions->isCached()) {
            $pages = $this->getCached($request, $sqlOptions);
        } else {
            $pages = $this->getEntities($sqlOptions);
        }

        if ($this->configOptions->isBitMask() === true) {
            $this->setFlagsIndex($pages);
        }

        return $this->response->setSqlOptions($sqlOptions)->get($pages, $meta);
    }

    /**
     * GET Output one entry determined by unique id as uri param
     *
     * @param Request $request
     * @param int|string $id
     * @return IlluminateResponse
     * @throws \SoliDry\Exceptions\AttributesException
     */
    public function view(Request $request, $id) : IlluminateResponse
    {
        $meta       = [];
        $sqlOptions = $this->setSqlOptions($request);
        $sqlOptions->setId($id);
        $data = $sqlOptions->getData();

        if ($this->isTree === true) {
            $tree = $this->getSubTreeEntities($sqlOptions, $id);
            $meta = [strtolower($this->entity) . PhpInterface::UNDERSCORE . JSONApiInterface::META_TREE => $tree];
        }

        if ($this->configOptions->isCached()) {
            $item = $this->getCached($request, $sqlOptions);
        } else {
            $item = $this->getEntity($id, $data);
        }

        if ($this->configOptions->isBitMask() === true) {
            $this->setFlagsView($item);
        }

        return $this->response->setSqlOptions($sqlOptions)->get($item, $meta);
    }

    /**
     * POST Creates one entry specified by all input fields in $request
     *
     * @param Request $request
     * @return IlluminateResponse
     * @throws \SoliDry\Exceptions\AttributesException
     */
    public function create(Request $request) : IlluminateResponse
    {
        $meta              = [];
        $json              = Json::decode($request->getContent());
        $jsonApiAttributes = Json::getAttributes($json);

        // FSM initial state check
        if ($this->configOptions->isStateMachine() === true) {
            $this->checkFsmCreate($jsonApiAttributes);
        }

        // spell check
        if ($this->configOptions->isSpellCheck() === true) {
            $meta = $this->spellCheck($jsonApiAttributes);
        }

        // fill in model
        foreach ($this->props as $k => $v) {
            // request fields should match FormRequest fields
            if (isset($jsonApiAttributes[$k])) {
                $this->model->$k = $jsonApiAttributes[$k];
            }
        }

        // set bit mask
        if ($this->configOptions->isBitMask() === true) {
            $this->setMaskCreate($jsonApiAttributes);
        }
        $this->model->save();

        // jwt
        if ($this->configOptions->getIsJwtAction() === true) {
            $this->createJwtUser(); // !!! model is overridden
        }

        // set bit mask from model -> response
        if ($this->configOptions->isBitMask() === true) {
            $this->model = $this->setFlagsCreate();
        }

        $this->setRelationships($json, $this->model->id);

        return $this->response->get($this->model, $meta);
    }

    /**
     * PATCH Updates one entry determined by unique id as uri param for specified fields in $request
     *
     * @param Request $request
     * @param int|string $id
     * @return IlluminateResponse
     * @throws \SoliDry\Exceptions\AttributesException
     */
    public function update(Request $request, $id) : IlluminateResponse
    {
        $meta = [];

        // get json raw input and parse attrs
        $json              = Json::decode($request->getContent());
        $jsonApiAttributes = Json::getAttributes($json);
        $model             = $this->getEntity($id);

        // FSM transition check
        if ($this->configOptions->isStateMachine() === true) {
            $this->checkFsmUpdate($jsonApiAttributes, $model);
        }

        // spell check
        if ($this->configOptions->isSpellCheck() === true) {
            $meta = $this->spellCheck($jsonApiAttributes);
        }

        $this->processUpdate($model, $jsonApiAttributes);
        $model->save();

        $this->setRelationships($json, $model->id, true);

        // set bit mask
        if ($this->configOptions->isBitMask() === true) {
            $this->setFlagsUpdate($model);
        }

        return $this->response->get($model, $meta);
    }

    /**
     * Process model update
     * @param $model
     * @param array $jsonApiAttributes
     * @throws \SoliDry\Exceptions\AttributesException
     */
    private function processUpdate($model, array $jsonApiAttributes)
    {
        // jwt
        $isJwtAction = $this->configOptions->getIsJwtAction();
        if ($isJwtAction === true && (bool)$jsonApiAttributes[JwtInterface::JWT] === true) {
            $this->updateJwtUser($model, $jsonApiAttributes);
        } else { // standard processing
            foreach ($this->props as $k => $v) {
                // request fields should match FormRequest fields
                if (empty($jsonApiAttributes[$k]) === false) {
                    if ($isJwtAction === true && $k === JwtInterface::PASSWORD) {// it is a regular query with password updated and jwt enabled - hash the password
                        $model->$k = password_hash($jsonApiAttributes[$k], PASSWORD_DEFAULT);
                    } else {
                        $model->$k = $jsonApiAttributes[$k];
                    }
                }
            }
        }

        // set bit mask
        if ($this->configOptions->isBitMask() === true) {
            $this->setMaskUpdate($model, $jsonApiAttributes);
        }
    }

    /**
     * DELETE Deletes one entry determined by unique id as uri param
     *
     * @param Request $request
     * @param int|string $id
     * @return IlluminateResponse
     */
    public function delete(Request $request, $id) : IlluminateResponse
    {
        $model = $this->getEntity($id);
        if ($model !== null) {
            $model->delete();
        }

        return $this->response->getResponse(Json::prepareSerializedData(new Collection()), JSONApiInterface::HTTP_RESPONSE_CODE_NO_CONTENT);
    }

    /**
     *  Adds {HTTPMethod}Relations to array of route methods
     */
    private function addRelationMethods()
    {
        $ucRelations            = ucfirst(JSONApiInterface::URI_METHOD_RELATIONS);
        $this->jsonApiMethods[] = JSONApiInterface::URI_METHOD_CREATE . $ucRelations;
        $this->jsonApiMethods[] = JSONApiInterface::URI_METHOD_UPDATE . $ucRelations;
        $this->jsonApiMethods[] = JSONApiInterface::URI_METHOD_DELETE . $ucRelations;
    }
}