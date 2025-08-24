@extends('layouts.app')

@section('title', '日時選択')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-gray-300 rounded-full">
                        <span class="text-white font-semibold">1</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-300"></div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-gray-300 rounded-full">
                        <span class="text-white font-semibold">2</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-300"></div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-primary-600 rounded-full">
                        <span class="text-white font-semibold">3</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-300"></div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-gray-300 rounded-full">
                        <span class="text-gray-600 font-semibold">4</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-300"></div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 bg-gray-300 rounded-full">
                    <span class="text-gray-600 font-semibold">5</span>
                </div>
            </div>
        </div>

        <!-- Selected Info -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">選択中の内容</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">店舗</p>
                    <p class="font-medium" id="selected-store-name">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">メニュー</p>
                    <p class="font-medium" id="selected-menu-name">-</p>
                </div>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Calendar -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="calendar-month-year"></h3>
                    <div class="flex space-x-2">
                        <button onclick="previousMonth()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button onclick="nextMonth()" class="p-2 hover:bg-gray-100 rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <div class="text-center text-xs font-medium text-gray-600 py-2">月</div>
                    <div class="text-center text-xs font-medium text-gray-600 py-2">火</div>
                    <div class="text-center text-xs font-medium text-gray-600 py-2">水</div>
                    <div class="text-center text-xs font-medium text-gray-600 py-2">木</div>
                    <div class="text-center text-xs font-medium text-gray-600 py-2">金</div>
                    <div class="text-center text-xs font-medium text-gray-600 py-2">土</div>
                    <div class="text-center text-xs font-medium text-red-600 py-2">日</div>
                </div>
                <div id="calendar-days" class="grid grid-cols-7 gap-1">
                    <!-- Calendar days will be generated here -->
                </div>

                <!-- Loading indicator -->
                <div id="calendar-loading" class="hidden py-8">
                    <div class="flex justify-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    </div>
                </div>
            </div>

            <!-- Time Slots -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">時間選択</h3>
                
                <!-- Selected Date Info -->
                <div id="selected-date-info" class="hidden mb-4 p-3 bg-primary-50 rounded-lg">
                    <p class="text-sm font-medium text-primary-900" id="selected-date-text"></p>
                </div>

                <!-- Time Slots Grid -->
                <div id="time-slots-container" class="space-y-4">
                    <p class="text-gray-500 text-center py-8">日付を選択してください</p>
                </div>

                <!-- Loading indicator -->
                <div id="loading-times" class="hidden">
                    <div class="flex justify-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selection Summary -->
        <div id="selection-summary" class="hidden mt-6 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">選択内容の確認</h3>
            <div class="grid md:grid-cols-4 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600">店舗</p>
                    <p class="font-medium" id="summary-store">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">メニュー</p>
                    <p class="font-medium" id="summary-menu">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">日付</p>
                    <p class="font-medium" id="summary-date">-</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">時間</p>
                    <p class="font-medium" id="summary-time">-</p>
                </div>
            </div>
            <div class="flex justify-between">
                <a href="/reservation/menu" class="bg-gray-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-600 transition-colors">
                    戻る
                </a>
                <button onclick="proceedToCustomer()" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors">
                    次へ進む
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentYear = new Date().getFullYear();
let currentMonth = new Date().getMonth();
let selectedDate = null;
let selectedTime = null;
let availableDays = {};
let selectedStore = null;
let selectedMenu = null;

document.addEventListener('DOMContentLoaded', async function() {
    // Get selected store and menu from session storage
    selectedStore = JSON.parse(sessionStorage.getItem('selectedStore') || '{}');
    selectedMenu = JSON.parse(sessionStorage.getItem('selectedMenu') || '{}');
    
    if (!selectedStore.id || !selectedMenu.id) {
        window.location.href = '/stores';
        return;
    }
    
    // Display selected info
    document.getElementById('selected-store-name').textContent = selectedStore.name || '-';
    document.getElementById('selected-menu-name').textContent = selectedMenu.name || '-';
    
    // Initialize calendar
    await loadAvailableDays();
    generateCalendar(currentYear, currentMonth);
});

async function loadAvailableDays() {
    try {
        const response = await fetch(`/api/availability/days?store_id=${selectedStore.id}&year=${currentYear}&month=${currentMonth + 1}`);
        const data = await response.json();
        
        if (response.ok) {
            // Convert array to object for easier lookup
            availableDays = {};
            data.available_days.forEach(day => {
                availableDays[day.date] = day;
            });
        }
    } catch (error) {
        console.error('Error loading available days:', error);
    }
}

async function previousMonth() {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    await loadAvailableDays();
    generateCalendar(currentYear, currentMonth);
}

async function nextMonth() {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    await loadAvailableDays();
    generateCalendar(currentYear, currentMonth);
}

function generateCalendar(year, month) {
    const monthNames = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Update month/year display
    document.getElementById('calendar-month-year').textContent = `${year}年 ${monthNames[month]}`;
    
    // Calculate start date (Monday of the week containing the 1st)
    const startDate = new Date(firstDay);
    const dayOfWeek = startDate.getDay();
    const daysToSubtract = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    startDate.setDate(startDate.getDate() - daysToSubtract);
    
    // Generate calendar days
    const daysContainer = document.getElementById('calendar-days');
    daysContainer.innerHTML = '';
    
    for (let i = 0; i < 42; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day p-2 text-center text-sm cursor-pointer rounded transition-colors';
        dayElement.textContent = date.getDate();
        
        const dateStr = date.toISOString().split('T')[0];
        const dayInfo = availableDays[dateStr];
        
        // Style different day types
        if (date.getMonth() !== month) {
            dayElement.className += ' text-gray-300';
        } else if (dayInfo && dayInfo.is_past) {
            dayElement.className += ' text-gray-400 cursor-not-allowed';
        } else if (dayInfo && dayInfo.is_closed) {
            dayElement.className += ' text-gray-400 cursor-not-allowed bg-gray-50';
            dayElement.title = '定休日';
        } else if (date < today) {
            dayElement.className += ' text-gray-400 cursor-not-allowed';
        } else {
            dayElement.className += ' text-gray-900 hover:bg-primary-50';
            dayElement.addEventListener('click', () => selectDate(date));
        }
        
        // Highlight selected date
        if (selectedDate && date.toDateString() === selectedDate.toDateString()) {
            dayElement.className += ' bg-primary-600 text-white';
        }
        
        // Sunday color
        if (date.getDay() === 0 && date.getMonth() === month) {
            dayElement.classList.add('text-red-600');
        }
        
        daysContainer.appendChild(dayElement);
    }
}

async function selectDate(date) {
    selectedDate = new Date(date);
    selectedTime = null;
    
    // Update calendar display
    generateCalendar(currentYear, currentMonth);
    
    // Update selected date info
    const dateInfo = document.getElementById('selected-date-info');
    const dateText = document.getElementById('selected-date-text');
    dateText.textContent = selectedDate.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    });
    dateInfo.classList.remove('hidden');
    
    // Load available time slots
    await loadTimeSlots(selectedDate);
    
    // Hide summary until time is selected
    document.getElementById('selection-summary').classList.add('hidden');
}

async function loadTimeSlots(date) {
    const container = document.getElementById('time-slots-container');
    const loading = document.getElementById('loading-times');
    
    container.innerHTML = '';
    loading.classList.remove('hidden');
    
    try {
        const dateStr = date.toISOString().split('T')[0];
        const response = await fetch(`/api/availability/slots?store_id=${selectedStore.id}&menu_id=${selectedMenu.id}&date=${dateStr}`);
        const data = await response.json();
        
        loading.classList.add('hidden');
        
        if (response.ok && data.available_slots.length > 0) {
            container.innerHTML = `
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    ${data.available_slots.map(slot => `
                        <button 
                            class="time-slot p-2 text-sm font-medium rounded transition-colors bg-gray-100 text-gray-900 hover:bg-primary-50 hover:text-primary-700 border border-gray-200"
                            onclick="selectTime('${slot.time}')"
                        >
                            ${slot.time}
                        </button>
                    `).join('')}
                </div>
                
                ${data.business_hours ? `
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-800">
                            営業時間: ${data.business_hours.open} - ${data.business_hours.close}
                        </p>
                    </div>
                ` : ''}
            `;
        } else if (data.message === 'この日は定休日です') {
            container.innerHTML = `
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <p class="text-gray-600">この日は定休日です</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="p-4 bg-yellow-50 rounded-lg text-center">
                    <p class="text-yellow-800">予約可能な時間がありません</p>
                </div>
            `;
        }
        
    } catch (error) {
        console.error('Error fetching time slots:', error);
        loading.classList.add('hidden');
        container.innerHTML = `
            <div class="p-4 bg-red-50 rounded-lg text-center">
                <p class="text-red-800">時間の取得に失敗しました</p>
            </div>
        `;
    }
}

function selectTime(time) {
    selectedTime = time;
    
    // Update all time slot buttons
    document.querySelectorAll('.time-slot').forEach(btn => {
        if (btn.textContent.trim() === time) {
            btn.className = 'time-slot p-2 text-sm font-medium rounded transition-colors bg-primary-600 text-white border border-primary-600';
        } else {
            btn.className = 'time-slot p-2 text-sm font-medium rounded transition-colors bg-gray-100 text-gray-900 hover:bg-primary-50 hover:text-primary-700 border border-gray-200';
        }
    });
    
    // Show summary
    updateSummary();
}

function updateSummary() {
    if (selectedDate && selectedTime) {
        document.getElementById('summary-store').textContent = selectedStore.name;
        document.getElementById('summary-menu').textContent = selectedMenu.name;
        document.getElementById('summary-date').textContent = selectedDate.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'long'
        });
        document.getElementById('summary-time').textContent = selectedTime;
        document.getElementById('selection-summary').classList.remove('hidden');
    }
}

function proceedToCustomer() {
    if (!selectedDate || !selectedTime) {
        alert('日付と時間を選択してください');
        return;
    }
    
    // Save selection to session storage
    sessionStorage.setItem('selectedDate', selectedDate.toISOString().split('T')[0]);
    sessionStorage.setItem('selectedTime', selectedTime);
    
    // Save combined datetime for customer page
    sessionStorage.setItem('selectedDateTime', JSON.stringify({
        date: selectedDate.toISOString().split('T')[0],
        time: selectedTime
    }));
    
    // Proceed to customer info
    window.location.href = '/reservation/customer';
}
</script>
@endsection