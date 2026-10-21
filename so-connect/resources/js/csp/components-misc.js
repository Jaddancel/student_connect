/*
 * Alpine components for the "components-misc" views, registered before Alpine.start().
 * They replace inline x-data objects/methods and multi-statement handlers,
 * which Alpine's CSP build (no 'unsafe-eval') cannot evaluate.
 */
export default function registerComponentsMisc(Alpine) {
    // components/ui/modal
    Alpine.data("uiModal", (opts = {}) => ({
        open: opts.open,
        init() {
            this.$watch('open', value => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'unset';
                }
            });
        }
    }));

    // components/common/preloader — `loaded` lives on the layout's <body> scope.
    Alpine.data("commonPreloader", () => ({
        init() {
            window.addEventListener('DOMContentLoaded', () => {setTimeout(() => this.loaded = false, 350)});
        },
    }));

    // components/common/table-dropdown (createPopper is resolved as a global, as before)
    Alpine.data("commonTableDropdown", () => ({
        isOpen: false,
        popperInstance: null,
        init() {
            this.$nextTick(() => {
                this.popperInstance = createPopper(this.$refs.button, this.$refs.content, {
                    placement: 'bottom-end',
                    strategy: 'fixed',
                    modifiers: [
                        {
                            name: 'offset',
                            options: {
                                offset: [0, 4],
                            },
                        },
                    ],
                });
            });
        },
        toggle() {
            this.isOpen = !this.isOpen;
            if (this.popperInstance) {
                this.popperInstance.update();
            }
        }
    }));

    // components/header/notification-dropdown
    Alpine.data("headerNotificationDropdown", (opts = {}) => ({
        dropdownOpen: false,
        notifying: opts.notifying,
        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
            this.notifying = false;
        },
        closeDropdown() {
            this.dropdownOpen = false;
        },
        handleItemClick() {
            this.closeDropdown();
        }
    }));

    // components/header/organization-switcher
    Alpine.data("headerOrganizationSwitcher", () => ({
        open: false,
        positionPanel() {
            const panel = this.$refs.panel;
            const btn = this.$refs.switcherButton;
            if (!panel || !btn) return;

            // xl and up keep the right-anchored dropdown (CSS handles it).
            if (window.innerWidth >= 1280) {
                panel.style.removeProperty('position');
                panel.style.removeProperty('top');
                panel.style.removeProperty('left');
                panel.style.removeProperty('width');
                return;
            }

            // Below xl the button can sit anywhere in the header row, so
            // clamp the panel to the viewport instead of anchoring to it.
            const rect = btn.getBoundingClientRect();
            const margin = 16;
            const width = Math.min(310, window.innerWidth - margin * 2);
            const left = Math.min(Math.max(rect.left, margin), window.innerWidth - width - margin);

            panel.style.position = 'fixed';
            panel.style.top = `${rect.bottom + 8}px`;
            panel.style.left = `${left}px`;
            panel.style.width = `${width}px`;
        },
        togglePanel() {
            this.positionPanel();
            this.open = !this.open;
        }
    }));

    // components/header/user-dropdown
    Alpine.data("headerUserDropdown", () => ({
        dropdownOpen: false,
        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
        },
        closeDropdown() {
            this.dropdownOpen = false;
        }
    }));

    // components/ecommerce/statistics-chart — date range picker
    Alpine.data("ecommerceStatisticsDatepicker", () => ({
        init() {
            flatpickr(this.$refs.datepicker, {
                mode: 'range',
                static: true,
                monthSelectorType: 'static',
                dateFormat: 'M j',
                defaultDate: [new Date(Date.now() - 6 * 24 * 60 * 60 * 1000), new Date()],
                prevArrow: '<svg class=\'stroke-current\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M15.25 6L9 12.25L15.25 18.5\' stroke=\'\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>',
                nextArrow: '<svg class=\'stroke-current\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M8.75 19L15 12.75L8.75 6.5\' stroke=\'\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>',
                onReady: (selectedDates, dateStr, instance) => {
                    instance.element.value = dateStr.replace('to', '-');
                    const customClass = instance.element.getAttribute('data-class');
                    if (instance.calendarContainer) {
                        instance.calendarContainer.classList.add(customClass);
                    }
                },
                onChange: (selectedDates, dateStr, instance) => {
                    instance.element.value = dateStr.replace('to', '-');
                },
            })
        }
    }));
    // components/tables/basic-tables/basic-tables-one (static demo data)
    Alpine.data("basicTablesOne", () => ({
        orders: [
            {
                id: 1,
                user: {
                    image: './images/user/user-17.jpg',
                    name: 'Lindsey Curtis',
                    role: 'Web Designer',
                },
                projectName: 'Agency Website',
                team: {
                    images: [
                        './images/user/user-22.jpg',
                        './images/user/user-23.jpg',
                        './images/user/user-24.jpg',
                    ],
                },
                budget: '3.9K',
                status: 'Active',
            },
            {
                id: 2,
                user: {
                    image: './images/user/user-18.jpg',
                    name: 'Kaiya George',
                    role: 'Project Manager',
                },
                projectName: 'Technology',
                team: {
                    images: [
                        './images/user/user-25.jpg',
                        './images/user/user-26.jpg',
                    ],
                },
                budget: '24.9K',
                status: 'Pending',
            },
            {
                id: 3,
                user: {
                    image: './images/user/user-19.jpg',
                    name: 'Zain Geidt',
                    role: 'Content Writer',
                },
                projectName: 'Blog Writing',
                team: {
                    images: [
                        './images/user/user-27.jpg',
                    ],
                },
                budget: '12.7K',
                status: 'Active',
            },
            {
                id: 4,
                user: {
                    image: './images/user/user-20.jpg',
                    name: 'Abram Schleifer',
                    role: 'Digital Marketer',
                },
                projectName: 'Social Media',
                team: {
                    images: [
                        './images/user/user-28.jpg',
                        './images/user/user-29.jpg',
                        './images/user/user-30.jpg',
                    ],
                },
                budget: '2.8K',
                status: 'Cancel',
            },
            {
                id: 5,
                user: {
                    image: './images/user/user-21.jpg',
                    name: 'Carla George',
                    role: 'Front-end Developer',
                },
                projectName: 'Website',
                team: {
                    images: [
                        './images/user/user-31.jpg',
                        './images/user/user-32.jpg',
                        './images/user/user-33.jpg',
                    ],
                },
                budget: '4.5K',
                status: 'Active',
            },
        ],
        getStatusClass(status) {
            const classes = {
                'Active': 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-500',
                'Pending': 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
                'Cancel': 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-500',
            };
            return classes[status] || '';
        }
    }));

    // components/tables/basic-tables/basic-tables-two (static demo data)
    Alpine.data("basicTablesTwo", () => ({
        tableRowData: [
            {
                id: 'DE124321',
                checked: false,
                customerName: 'John Doe',
                customerEmail: 'johndoe@gmail.com',
                initials: 'JD',
                avatarBg: 'bg-blue-100',
                avatarColor: 'text-blue-500',
                product: 'Software License',
                value: '$18,50.34',
                closeDate: '2024-06-15',
                status: 'Complete',
            },
            {
                id: 'DE124322',
                checked: false,
                customerName: 'Kierra Franci',
                customerEmail: 'kierra@gmail.com',
                initials: 'KF',
                avatarBg: 'bg-[#fdf2fa]',
                avatarColor: 'text-[#dd2590]',
                product: 'Software License',
                value: '$18,50.34',
                closeDate: '2024-06-15',
                status: 'Complete',
            },
            {
                id: 'DE124323',
                checked: false,
                customerName: 'Emerson Workman',
                customerEmail: 'emerson@gmail.com',
                initials: 'EW',
                avatarBg: 'bg-[#f0f9ff]',
                avatarColor: 'text-[#0086c9]',
                product: 'Software License',
                value: '$18,50.34',
                closeDate: '2024-06-15',
                status: 'Pending',
            },
            {
                id: 'DE124324',
                checked: false,
                customerName: 'Chance Philips',
                customerEmail: 'chance@gmail.com',
                initials: 'CP',
                avatarBg: 'bg-[#fff6ed]',
                avatarColor: 'text-[#ec4a0a]',
                product: 'Software License',
                value: '$18,50.34',
                closeDate: '2024-06-15',
                status: 'Complete',
            },
            {
                id: 'DE124325',
                checked: false,
                customerName: 'Terry Geidt',
                customerEmail: 'terry@gmail.com',
                initials: 'TG',
                avatarBg: 'bg-green-50',
                avatarColor: 'text-green-600',
                product: 'Software License',
                value: '$18,50.34',
                closeDate: '2024-06-15',
                status: 'Complete',
            },
        ],
        selectedRows: [],
        selectAll: false,
        handleSelectAll() {
            this.selectAll = !this.selectAll;
            if (this.selectAll) {
                this.selectedRows = this.tableRowData.map(row => row.id);
            } else {
                this.selectedRows = [];
            }
        },
        handleRowSelect(id) {
            if (this.selectedRows.includes(id)) {
                this.selectedRows = this.selectedRows.filter(rowId => rowId !== id);
            } else {
                this.selectedRows.push(id);
            }
        },
        getStatusClass(status) {
            const classes = {
                'Complete': 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-500',
                'Pending': 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
                'Cancel': 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-500',
            };
            return classes[status] || '';
        },
        deleteRow(id) {
            if (confirm('Are you sure you want to delete this order?')) {
                this.tableRowData = this.tableRowData.filter(row => row.id !== id);
                this.selectedRows = this.selectedRows.filter(rowId => rowId !== id);
            }
        }
    }));

    // components/tables/basic-tables/basic-tables-three (static demo data)
    Alpine.data("basicTablesThree", () => ({
        transactions: [
            {
                id: 1,
                name: 'Bought PYPL',
                image: '/images/brand/brand-08.svg',
                date: 'Nov 23, 01:00 PM',
                price: '$2,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 2,
                name: 'Bought AAPL',
                image: '/images/brand/brand-07.svg',
                date: 'Nov 23, 01:00 PM',
                price: '$2,567.88',
                category: 'Finance',
                status: 'Pending',
            },
            {
                id: 3,
                name: 'Sell KKST',
                image: '/images/brand/brand-15.svg',
                date: 'Nov 23, 01:00 PM',
                price: '$2,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 4,
                name: 'Bought FB',
                image: '/images/brand/brand-02.svg',
                date: 'Nov 23, 01:00 PM',
                price: '$2,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 5,
                name: 'Sell AMZN',
                image: '/images/brand/brand-10.svg',
                date: 'Nov 23, 01:00 PM',
                price: '$2,567.88',
                category: 'Finance',
                status: 'Failed',
            },
            {
                id: 6,
                name: 'Bought MSFT',
                image: '/images/brand/brand-09.svg',
                date: 'Nov 22, 01:00 PM',
                price: '$1,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 7,
                name: 'Bought GOOG',
                image: '/images/brand/brand-01.svg',
                date: 'Nov 22, 01:00 PM',
                price: '$3,567.88',
                category: 'Finance',
                status: 'Pending',
            },
            {
                id: 8,
                name: 'Sell TSLA',
                image: '/images/brand/brand-12.svg',
                date: 'Nov 22, 01:00 PM',
                price: '$4,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 9,
                name: 'Bought NVDA',
                image: '/images/brand/brand-11.svg',
                date: 'Nov 22, 01:00 PM',
                price: '$5,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 10,
                name: 'Sell META',
                image: '/images/brand/brand-03.svg',
                date: 'Nov 22, 01:00 PM',
                price: '$6,567.88',
                category: 'Finance',
                status: 'Failed',
            },
            {
                id: 11,
                name: 'Bought DIS',
                image: '/images/brand/brand-04.svg',
                date: 'Nov 21, 01:00 PM',
                price: '$7,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 12,
                name: 'Bought NFLX',
                image: '/images/brand/brand-05.svg',
                date: 'Nov 21, 01:00 PM',
                price: '$8,567.88',
                category: 'Finance',
                status: 'Pending',
            },
            {
                id: 13,
                name: 'Sell CRM',
                image: '/images/brand/brand-06.svg',
                date: 'Nov 21, 01:00 PM',
                price: '$9,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 14,
                name: 'Bought TSLA',
                image: '/images/brand/brand-13.svg',
                date: 'Nov 21, 01:00 PM',
                price: '$10,567.88',
                category: 'Finance',
                status: 'Success',
            },
            {
                id: 15,
                name: 'Sell AAPL',
                image: '/images/brand/brand-14.svg',
                date: 'Nov 21, 01:00 PM',
                price: '$11,567.88',
                category: 'Finance',
                status: 'Failed',
            },
        ],
        itemsPerPage: 5,
        currentPage: 1,
        dropdownOpen: null,
        get totalPages() {
            return Math.ceil(this.transactions.length / this.itemsPerPage);
        },
        get paginatedTransactions() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.transactions.slice(start, end);
        },
        get displayedPages() {
            const range = [];
            for (let i = 1; i <= this.totalPages; i++) {
                if (
                    i === 1 ||
                    i === this.totalPages ||
                    (i >= this.currentPage - 1 && i <= this.currentPage + 1)
                ) {
                    range.push(i);
                } else if (range[range.length - 1] !== '...') {
                    range.push('...');
                }
            }
            return range;
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        goToPage(page) {
            if (typeof page === 'number' && page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },
        getStatusClass(status) {
            const classes = {
                'Success': 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-500',
                'Pending': 'bg-yellow-50 text-yellow-600 dark:bg-yellow-500/15 dark:text-orange-400',
                'Failed': 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-500',
            };
            return classes[status] || '';
        },
        toggleDropdown(id) {
            this.dropdownOpen = this.dropdownOpen === id ? null : id;
        }
    }));

    // pages/profile/create
    Alpine.data("profileSetupForm", (opts = {}) => ({
        sex: opts.sex,
        birthday: opts.birthday,
        age: opts.age,
        computeAge() {
            if (!this.birthday) return;
            const today = new Date();
            const dob = new Date(this.birthday);
            let years = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) years--;
            this.age = years > 0 ? years : '';
        }
    }));
}
