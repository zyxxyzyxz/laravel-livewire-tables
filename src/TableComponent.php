<?php

namespace Rappasoft\LaravelLivewireTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Rappasoft\LaravelLivewireTables\Traits\Checkboxes;
use Rappasoft\LaravelLivewireTables\Traits\Loading;
use Rappasoft\LaravelLivewireTables\Traits\Offline;
use Rappasoft\LaravelLivewireTables\Traits\Pagination;
use Rappasoft\LaravelLivewireTables\Traits\Search;
use Rappasoft\LaravelLivewireTables\Traits\Sorting;
use Rappasoft\LaravelLivewireTables\Traits\Table;
use Rappasoft\LaravelLivewireTables\Traits\Yajra;

/**
 * Class TableComponent.
 */
abstract class TableComponent extends Component
{
    use Checkboxes,
        Loading,
        Offline,
        Pagination,
        Search,
        Sorting,
        Table,
        WithPagination,
        Yajra;

    /**
     * The classes applied to the wrapper div.
     *
     * @var string
     */
    public $wrapperClass = '';

    /**
     * Whether or not to refresh the table at a certain interval
     * false is off
     * If it's an integer it will be treated as milliseconds (2000 = refresh every 2 seconds)
     * If it's a string it will call that function every 5 seconds.
     *
     * @var bool|string
     */
    public $refresh = false;

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract public function query(): Builder;

    /**
     * @return array
     */
    abstract public function columns(): array;

    /**
     * @return string
     */
    public function view(): string
    {
        return 'laravel-livewire-tables::table-component';
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function render(): View
    {
        return view($this->view(), [
            'columns' => $this->columns(),
            'models' => $this->paginationEnabled ? $this->models()->paginate($this->perPage) : $this->models()->get(),
        ]);
    }

    /**
     * @return Builder
     */
    public function models(): Builder
    {
        $builder = $this->query();

        if ($this->searchEnabled && trim($this->search) !== '') {
            $builder->where(function (Builder $builder) {
                foreach ($this->columns() as $column) {
                    if ($column->searchable) {
                        if (is_callable($column->searchCallback)) {
                            $builder = app()->call($column->searchCallback, ['builder' => $builder, 'term' => trim($this->search)]);
                        } elseif (Str::contains($column->attribute, '.')) {
                            $relationship = $this->relationship($column->attribute);

                            $builder->orWhereHas($relationship->name, function (Builder $builder) use ($relationship) {
                                $builder->where($relationship->attribute, 'like', '%'.trim($this->search).'%');
                            });
                        } else {
                            $builder->orWhere($builder->getModel()->getTable().'.'.$column->attribute, 'like', '%'.trim($this->search).'%');
                        }
                    }
                }
            });
        }

        if (Str::contains($this->sortField, '.')) {
            $relationship = $this->relationship($this->sortField);
            $sortField = $this->attribute($builder, $relationship->name, $relationship->attribute);
        } else {
            $sortField = $this->sortField;
        }

        if (($column = $this->getColumnByAttribute($this->sortField)) !== null && is_callable($column->sortCallback)) {
            return app()->call($column->sortCallback, ['builder' => $builder, 'direction' => $this->sortDirection]);
        }

        return $builder->orderBy($sortField, $this->sortDirection);
    }
}
