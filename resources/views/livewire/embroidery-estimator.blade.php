<div class="max-w-xl mx-auto p-6 bg-white rounded-2xl shadow">
  <h2 class="text-xl font-bold mb-4">Embroidery Estimator</h2>

  <form wire:submit.prevent="estimate" class="space-y-4">
    <input type="file" wire:model="design" type="file"
      class="block text-sm border rounded file:rounded border-gray-500 text-gray-500
   file:mr-2 file:py-1 file:px-3
   file:text-xs file:font-medium
   file:bg-gray-50 file:text-gray-700
   hover:file:cursor-pointer hover:file:bg-blue-50
   hover:file:text-blue-700" />

    @error('design')
      <p class="text-red-500 text-sm">{{ $message }}</p>
    @enderror

    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl shadow text-sm hover:bg-blue-700 cursor-pointer" wire:loading.class="hidden">
      Oblicz
    </button>

    <div wire:loading>
      Ładowanie...
    </div>
  </form>

  @if ($result)
    <div class="mt-6 p-4 bg-gray-100 rounded-xl">
      <h3 class="font-semibold mb-2">Wynik</h3>

      @if ($design)
        <div class="mb-3 max-w-24 max-h-24">
          <img src="{{ $previewImage }}" class="rounded shadow">
        </div>
      @endif

      <ul class="space-y-1 text-sm">
        <li><strong>Szerokość:</strong> {{ round($result['width_cm'], 1) }} cm</li>
        <li><strong>Wysokość:</strong> {{ round($result['height_cm'], 1) }} cm</li>
        <li><strong>Powierzchnia:</strong> {{ $result['area_cm2'] }} cm²</li>
        <li><strong>Pokrycie:</strong> ~{{ $result['coverage'] }}</li>
        <li><strong>Szacowana liczba ściegów:</strong> {{ number_format($result['estimated_stitches']) }}</li>
        <li><strong>Czas haftowania (bez przygotowania):</strong> {{ $result['production_time'] }}</li>
        <li><strong>Zużycie nici:</strong> {{ $result['thread_usage'] }}</li>
        {{-- <li class="text-blue-700 font-bold"><strong>Szacowana cena:</strong> {{ $result['price'] }}</li> --}}
      </ul>

      <p class="mt-2 text-xs text-yellow-600">
        ⚠️ Wynik jest jedynie przybliżony. Rzeczywiste wartości zależą od programu, gęstości, prędkości maszyny itp. Warto przyjąć tolerancję +/- 20%.
      </p>
    </div>
  @endif
</div>
