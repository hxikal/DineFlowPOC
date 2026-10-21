<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigurationRequest;
use App\Models\OutletSection;
use App\Services\ConfigurationManager;
use App\Services\OutletContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index(Request $request, string $type, ConfigurationManager $manager, OutletContext $context, ?int $record = null): View
    {
        $model = $manager->model($type);
        $scoped = in_array($type, ['sections', 'tables'], true);
        $outlet = $context->current($request);
        $query = $model->newQuery();
        if ($scoped) {
            $query->where('outlet_id', $outlet?->getKey() ?? 0);
        }
        $editing = $record ? (clone $query)->whereKey($record)->firstOrFail() : null;

        return view('admin.configuration', ['type' => $type, 'records' => $query->orderBy($model->getKeyName())->paginate(20), 'editing' => $editing, 'outlet' => $outlet, 'sections' => OutletSection::where('outlet_id', $outlet?->getKey() ?? 0)->orderBy('sort_order')->get()]);
    }

    public function store(ConfigurationRequest $request, string $type, ConfigurationManager $manager, OutletContext $context): RedirectResponse
    {
        $manager->save($request->user(), $type, null, $request->validated(), $context->current($request)?->getKey());

        return redirect()->route('configuration.index', $type)->with('success', __('admin.saved'));
    }

    public function update(ConfigurationRequest $request, string $type, int $record, ConfigurationManager $manager, OutletContext $context): RedirectResponse
    {
        $manager->save($request->user(), $type, $record, $request->validated(), $context->current($request)?->getKey());

        return redirect()->route('configuration.index', $type)->with('success', __('admin.saved'));
    }
}
