{{-- Budget Tool (Finance pack) — embedded as content in the Workspace
     tool rail (ADR 0007). The rail provides the drawer chrome + close;
     this renders the editor body inline. The share modal renders inline
     (no x-teleport: it would break on the rail's mount/unmount morph, and
     the rail is already a positioned panel). --}}
<div>
    @include('finance::_budget-body')
    @include('finance::_budget-share-modal')
</div>
