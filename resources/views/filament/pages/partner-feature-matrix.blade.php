<x-filament-panels::page>
    <form wire:submit.prevent="save">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/10">
                            <th class="w-64 px-4 py-3 text-left font-semibold text-gray-500">Tính năng / Giới hạn</th>
                            @foreach ($this->plans() as $plan)
                                <th class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                                    {{ $plan->name }}
                                    <div class="mt-0.5 text-xs font-normal text-gray-400">
                                        {{ number_format($plan->price, 0, ',', '.') }}đ/tháng
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-100 dark:border-white/10">
                            <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số chi nhánh tối đa</td>
                            @foreach ($this->plans() as $plan)
                                <td class="px-4 py-3 text-center">
                                    <input type="number" min="1"
                                           wire:model="matrix.{{ $plan->id }}.max_branches"
                                           placeholder="Không giới hạn"
                                           class="w-28 rounded-lg border-gray-300 text-center text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                </td>
                            @endforeach
                        </tr>

                        @foreach (\App\Filament\Resources\PartnerPlanResource::FEATURE_OPTIONS as $key => $label)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/10">
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $label }}</td>
                                @foreach ($this->plans() as $plan)
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox"
                                               wire:model="matrix.{{ $plan->id }}.features.{{ $key }}"
                                               class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <p class="text-xs text-gray-400">
                Để trống "Số chi nhánh tối đa" = không giới hạn. Muốn thêm/sửa tên, giá, thứ tự hiển thị hoặc thêm gói mới, vào mục "Gói đối tác".
            </p>
            <x-filament::button type="submit">
                Lưu thay đổi
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
