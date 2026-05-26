<x-app-layout>
    <x-slot name="title">Edit Asuransi</x-slot>

    <div class="page-content">
        <livewire:pengaturan.asuransi.asuransi-form :id="$asuransi->id" />
    </div>
</x-app-layout>
