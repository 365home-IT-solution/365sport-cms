<x-filament-panels::page>
    @php $cameras = $this->getCameras(); @endphp

    @if ($cameras->isEmpty())
        <div class="text-center text-gray-500 py-12">
            Chưa có camera nào đang hoạt động. Vào "Camera an ninh" để thêm camera.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($cameras as $camera)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 flex items-center justify-between">
                        <span class="font-medium text-sm">{{ $camera['name'] }}</span>
                        <span class="text-xs text-gray-500">{{ $camera['branch'] ?? '—' }}</span>
                    </div>

                    @if ($camera['configured'])
                        <video-rtc
                            src="{{ $camera['ws_url'] }}"
                            mode="mse"
                            style="width: 100%; aspect-ratio: 16/9; background: #000; display: block;"
                        ></video-rtc>
                    @else
                        <div style="width: 100%; aspect-ratio: 16/9;" class="flex items-center justify-center bg-gray-900 text-gray-400 text-sm text-center p-4">
                            Chưa cấu hình server go2rtc/Frigate cho đối tác này.<br>
                            Vào Cấu hình web &gt; Cấu hình Camera để thiết lập.
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <script src="{{ asset('vendor/go2rtc/video-rtc.js') }}"></script>
    @endif
</x-filament-panels::page>
