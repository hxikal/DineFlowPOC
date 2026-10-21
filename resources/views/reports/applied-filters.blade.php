<div class="report-applied"><strong><x-reportword key="filters" />:</strong>
@foreach($filters as $key=>$value)
    @if($value!==null && $value!=='')
        <span><x-reportword :key="$key" />:
        @if(in_array($key,['status','sort','direction']))
            <x-reportword :key="$value" />
        @else
            {{ $filterNames[$key] ?? $value }}
        @endif
        </span>
    @endif
@endforeach
</div>
