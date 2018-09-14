<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2018, Solspace, Inc.
 * @link          https://solspace.com/craft/freeform
 * @license       https://solspace.com/software/license-agreement
 */

namespace Solspace\FreeformPaymets\Library\Traits;

use craft\base\Model;

trait ModelCacheTrait
{
    /**
     * Model cache store
     *
     * @var Model[]
     */
    protected $modelCache;

    /**
     * Saves model to cache
     *
     * @param Model $model
     *
     * @return void
     */
    protected function cacheSave(Model $model)
    {
        $this->modelCache[$model->id] = $model;
    }

    /**
     * Deletes cached model from cache
     *
     * @param integer $id
     *
     * @return void
     */
    protected function cacheDelete(int $id)
    {
        if (isset($this->modelCache[$id])) {
            unset($this->modelCache[$id]);
        }
    }

    /**
     * Resets cache
     *
     * @return void
     */
    protected function cacheClear()
    {
        $this->modelCache = [];
    }

    /**
     * Returns cached model or null if id not cached
     *
     * @param integer $id
     *
     * @return Model|null
     */
    protected function cacheGet(int $id)
    {
        return $this->modelCache[$id] ?? null;
    }
}
