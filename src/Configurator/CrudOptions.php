<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\CmsBundle\Configurator;

use Doctrine\ORM\QueryBuilder;

/**
 * PHPStan type definitions for CRUD controller options.
 *
 * These types describe the shape of the $options array accepted by
 * {@see \ChamberOrchestra\CmsBundle\Controller\AbstractCrudController::__construct()}.
 *
 * All keys are optional at construction time — required keys (like `class`)
 * are validated by the {@see CrudControllerConfigurator} at resolve time.
 *
 * @phpstan-type IndexOptions array{
 *     fields?: ?array<int|string, string|array<string, mixed>>,
 *     actions?: ?callable,
 *     filter?: ?class-string,
 *     filter_mapping?: array<string, string|callable>,
 *     query_builder?: QueryBuilder|\Closure|null,
 *     order_by?: array<string, 'ASC'|'DESC'>,
 *     order_by_mapping?: ?array<int|string, string|callable|false>,
 *     export?: ?array<string, mixed>,
 *     alias?: ?array<string, mixed>,
 *     bulk_operation?: bool,
 *     bulk_nav?: ?callable,
 *     view?: string,
 *     view_params?: array<string, mixed>,
 *     route?: string,
 *     route_params?: array<string, mixed>,
 *     title?: ?string,
 * }
 * @phpstan-type ViewActionOptions array{
 *     title?: ?string,
 *     view?: string,
 *     view_params?: array<string, mixed>,
 *     route?: string,
 *     route_params?: array<string, mixed>,
 * }
 * @phpstan-type RouteActionOptions array{
 *     route?: string,
 *     route_params?: array<string, mixed>,
 * }
 * @phpstan-type Options array{
 *     class?: class-string,
 *     controller_class?: class-string,
 *     translation_domain?: string,
 *     parent_controller_class?: ?class-string,
 *     parent?: ?object,
 *     _parent?: object|callable|string|null,
 *     entity_label?: ?callable,
 *     label_format?: ?string,
 *     form_class?: ?string,
 *     data_class?: callable|string|null,
 *     form_themes?: list<string>,
 *     route_prefix?: ?string,
 *     route_params?: array<string, mixed>,
 *     view_prefix?: ?string,
 *     view_params?: array<string, mixed>,
 *     title?: ?string,
 *     nav?: bool|callable|string|null,
 *     breadcrumbs?: bool|callable|string|null,
 *     index?: IndexOptions,
 *     create?: ?ViewActionOptions,
 *     update?: ?ViewActionOptions,
 *     view?: ?ViewActionOptions,
 *     meta?: ?ViewActionOptions,
 *     export?: ?ViewActionOptions,
 *     delete?: ?RouteActionOptions,
 *     bulk_delete?: ?RouteActionOptions,
 *     move?: ?RouteActionOptions,
 *     toggle?: ?RouteActionOptions,
 *     copy?: ?RouteActionOptions,
 * }
 */
final class CrudOptions
{
}
