const DASHBOARD_TYPES = {
	// New User (sign-up) + Organization Membership requests.
	membership: {
		code: 1,
		section: 'membership',
		label: 'Memberships',
		singularLabel: 'Membership Request',
	},
	// Activity requests (form bound to the New Event function).
	events: {
		code: 2,
		section: 'events',
		label: 'Events',
		singularLabel: 'Activity Request',
	},
	// Requests from forms required by the accreditation conditions settings.
	roles: {
		code: 7,
		section: 'policy',
		label: 'Policy and Security',
		singularLabel: 'Accreditation Request',
	},
};

const DASHBOARD_TYPE_KEYS = Object.keys(DASHBOARD_TYPES);
const DASHBOARD_WINDOWS = {
	day: {
		key: 'day',
		label: 'Last 24 hours',
		metricLabel: 'Last 24h',
		windowLabel: '24 hours',
		chartWindowLabel: '24h window',
		compareWindowText: 'in the last 24 hours',
		listWindowText: 'in the last 24 hours',
		queryHours: 24,
		series: 'hourly',
	},
	month: {
		key: 'month',
		label: '1 month',
		metricLabel: 'Last month',
		windowLabel: '1 month',
		chartWindowLabel: '1 month',
		compareWindowText: 'in the last month',
		listWindowText: 'in the last month',
		queryHours: 24 * 30,
		series: 'daily',
	},
	all: {
		key: 'all',
		label: 'Overall',
		metricLabel: 'Overall',
		windowLabel: 'Overall',
		chartWindowLabel: 'overall',
		compareWindowText: 'overall',
		listWindowText: 'across all time',
		queryHours: null,
		series: 'monthly',
	},
};
const DASHBOARD_WINDOW_KEYS = Object.keys(DASHBOARD_WINDOWS);
const RECENT_LIMIT = 10;

function safeText(value, fallback = 'N/A') {
	if (value === null || value === undefined || value === '') {
		return fallback;
	}

	return String(value);
}

function normalizeDate(dateString) {
	const date = new Date(dateString);

	if (Number.isNaN(date.getTime())) {
		return null;
	}

	return date;
}

function formatRelativeTimestamp(dateString) {
	const parsed = normalizeDate(dateString);

	if (!parsed) {
		return 'Unknown Time';
	}

	return parsed.toLocaleString(undefined, {
		month: 'short',
		day: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
	});
}

function buildRequesterName(profile) {
	if (!profile || typeof profile !== 'object') {
		return 'Unknown Requester';
	}

	const first = safeText(profile.first_name, '').trim();
	const last = safeText(profile.last_name, '').trim();
	const fullName = `${first} ${last}`.trim();

	return fullName || 'Unknown Requester';
}

function buildHourlySeries(requests, windowHours = 24) {
	const now = new Date();
	const buckets = [];

	for (let offset = windowHours - 1; offset >= 0; offset -= 1) {
		const bucket = new Date(now);
		bucket.setMinutes(0, 0, 0);
		bucket.setHours(bucket.getHours() - offset);

		buckets.push({
			label: bucket.toLocaleTimeString([], {
				hour: 'numeric',
			}),
			from: bucket,
			to: new Date(bucket.getTime() + 60 * 60 * 1000),
			count: 0,
		});
	}

	for (const request of requests) {
		const at = normalizeDate(request.requested_at);

		if (!at) {
			continue;
		}

		const bucket = buckets.find((item) => at >= item.from && at < item.to);

		if (bucket) {
			bucket.count += 1;
		}
	}

	return buckets;
}

function buildDailySeries(requests, days = 30) {
	const now = new Date();
	const buckets = [];

	for (let offset = days - 1; offset >= 0; offset -= 1) {
		const bucket = new Date(now);
		bucket.setHours(0, 0, 0, 0);
		bucket.setDate(bucket.getDate() - offset);

		buckets.push({
			label: bucket.toLocaleDateString(undefined, {
				month: 'short',
				day: 'numeric',
			}),
			from: bucket,
			to: new Date(bucket.getTime() + 24 * 60 * 60 * 1000),
			count: 0,
		});
	}

	for (const request of requests) {
		const at = normalizeDate(request.requested_at);

		if (!at) {
			continue;
		}

		const bucket = buckets.find((item) => at >= item.from && at < item.to);

		if (bucket) {
			bucket.count += 1;
		}
	}

	return buckets;
}

const MAX_OVERALL_BUCKETS = 24;
const VOLUME_LABEL_COUNT = 6;
const VOLUME_UNITS = {
	hour: { legend: 'Requests per hour', suffix: '/hour' },
	day: { legend: 'Requests per day', suffix: '/day' },
	month: { legend: 'Requests per month', suffix: '/month' },
	quarter: { legend: 'Requests per quarter', suffix: '/quarter' },
	year: { legend: 'Requests per year', suffix: '/year' },
};

function shortYear(year) {
	return `'${String(year).slice(-2)}`;
}

// Whole history, bucketed by month, quarter or year so it never exceeds
// MAX_OVERALL_BUCKETS bars.
function buildOverallSeries(requests) {
	const now = new Date();
	const parsedTimes = requests
		.map((requestItem) => normalizeDate(requestItem.requested_at))
		.filter((dateValue) => dateValue instanceof Date);
	const earliest = parsedTimes.length
		? parsedTimes.reduce((minValue, currentValue) => (currentValue < minValue ? currentValue : minValue))
		: now;
	const latest = parsedTimes.reduce((maxValue, currentValue) => (currentValue > maxValue ? currentValue : maxValue), now);
	const monthIndex = (date) => date.getFullYear() * 12 + date.getMonth();
	const spanMonths = monthIndex(latest) - monthIndex(earliest) + 1;

	let unit = 'month';
	let monthsPerBucket = 1;

	if (spanMonths > MAX_OVERALL_BUCKETS * 3) {
		unit = 'year';
		monthsPerBucket = 12;
	} else if (spanMonths > MAX_OVERALL_BUCKETS) {
		unit = 'quarter';
		monthsPerBucket = 3;
	}

	const bucketIndex = (date) => Math.floor(monthIndex(date) / monthsPerBucket);
	const firstIndex = bucketIndex(earliest);
	const lastIndex = bucketIndex(latest);
	const buckets = [];

	for (let index = firstIndex; index <= lastIndex; index += 1) {
		const startMonth = index * monthsPerBucket;
		const year = Math.floor(startMonth / 12);
		const month = startMonth % 12;
		let label;

		if (unit === 'year') {
			label = String(year);
		} else if (unit === 'quarter') {
			label = `Q${Math.floor(month / 3) + 1} ${shortYear(year)}`;
		} else {
			label = `${new Date(year, month, 1).toLocaleDateString(undefined, { month: 'short' })} ${shortYear(year)}`;
		}

		buckets.push({ label, count: 0, unit });
	}

	for (const at of parsedTimes) {
		const bucket = buckets[bucketIndex(at) - firstIndex];

		if (bucket) {
			bucket.count += 1;
		}
	}

	return buckets;
}

function buildVolumeSeries(requests, windowType) {
	let buckets;

	if (windowType === 'daily') {
		buckets = buildDailySeries(requests, 30).map((bucket) => ({ ...bucket, unit: 'day' }));
	} else if (windowType === 'monthly') {
		buckets = buildOverallSeries(requests);
	} else {
		buckets = buildHourlySeries(requests, 24).map((bucket) => ({ ...bucket, unit: 'hour' }));
	}

	// Label roughly VOLUME_LABEL_COUNT evenly spaced bars (always the latest)
	// so dense series stay readable; every bar keeps its tooltip.
	const step = Math.max(Math.ceil(buckets.length / VOLUME_LABEL_COUNT), 1);
	const lastIndex = buckets.length - 1;

	return buckets.map((bucket, index) => ({
		...bucket,
		key: `${bucket.unit}-${index}-${bucket.label}`,
		showLabel: (lastIndex - index) % step === 0,
	}));
}

function getApprovalStatusLabel(status) {
	if (status === 'approved') {
		return 'Approved';
	}

	if (status === 'rejected') {
		return 'Rejected';
	}

	return 'Pending';
}

function emptyRoleStats() {
	return {
		organizationNames: [],
		organizationLabel: 'Your Organizations',
		adminsCount: 0,
		officersCount: 0,
		membershipsCount: 0,
		pendingRoleChangeRequestsCount: 0,
	};
}

document.addEventListener('alpine:init', () => {
	const Alpine = window.Alpine;

	Alpine.store('dashboardType', {
		typeCode: 'membership',
		all: DASHBOARD_TYPES,

		get current() {
			return this.all[this.typeCode] ?? this.all.membership;
		},

		isActive(key) {
			return this.typeCode === key;
		},

		async toggleType(typeCode) {
			if (!DASHBOARD_TYPE_KEYS.includes(typeCode) || this.typeCode === typeCode) {
				return;
			}

			this.typeCode = typeCode;
			await Alpine.store('dashboardData').load();
		},
	});

	Alpine.store('dashboardWindow', {
		key: 'day',
		all: DASHBOARD_WINDOWS,

		get current() {
			return this.all[this.key] ?? this.all.day;
		},

		async setWindow(windowKey) {
			if (!DASHBOARD_WINDOW_KEYS.includes(windowKey) || this.key === windowKey) {
				return;
			}

			this.key = windowKey;
			await Alpine.store('dashboardData').load();
		},
	});

	Alpine.store('dashboardData', {
		loading: false,
		loaded: false,
		error: null,
		activeRequestId: 0,
		decisionRequestId: null,
		requests: [],
		volumeSeries: [],
		roleStats: emptyRoleStats(),

		get type() {
			return Alpine.store('dashboardType').current;
		},

		get window() {
			return Alpine.store('dashboardWindow').current;
		},

		get metricWindowLabel() {
			return this.window.metricLabel;
		},

		get windowLabel() {
			return this.window.windowLabel;
		},

		get chartWindowLabel() {
			return this.window.chartWindowLabel;
		},

		get compareWindowText() {
			return this.window.compareWindowText;
		},

		get listWindowText() {
			return this.window.listWindowText;
		},

		get volumeUnit() {
			return VOLUME_UNITS[this.volumeSeries[0]?.unit] ?? VOLUME_UNITS.hour;
		},

		get volumeLegendLabel() {
			return this.volumeUnit.legend;
		},

		get peakVolumeSuffix() {
			return this.volumeUnit.suffix;
		},

		get totalRequests() {
			return this.requests.length;
		},

		get recentRequests() {
			return this.requests.slice(0, RECENT_LIMIT);
		},

		get approvedCount() {
			return this.requests.filter((requestItem) => requestItem.approval_status === 'approved').length;
		},

		get deniedCount() {
			return this.requests.filter((requestItem) => requestItem.approval_status === 'rejected').length;
		},

		get pendingCount() {
			return Math.max(this.totalRequests - this.approvedCount - this.deniedCount, 0);
		},

		get approvalRate() {
			if (this.totalRequests === 0) {
				return 0;
			}

			return Math.round((this.approvedCount / this.totalRequests) * 100);
		},

		get denialRate() {
			if (this.totalRequests === 0) {
				return 0;
			}

			return Math.round((this.deniedCount / this.totalRequests) * 100);
		},

		get completionRate() {
			if (this.totalRequests === 0) {
				return 0;
			}

			return Math.round(((this.approvedCount + this.deniedCount) / this.totalRequests) * 100);
		},

		get maxVolumeCount() {
			return Math.max(...this.volumeSeries.map((item) => item.count), 1);
		},

		isDecisionLoading(requestId) {
			return this.decisionRequestId === requestId;
		},

		async decideRequest(requestId, decision) {
			const normalizedRequestId = Number.parseInt(String(requestId), 10);

			if (!Number.isInteger(normalizedRequestId) || normalizedRequestId <= 0) {
				return;
			}

			if (!['approve', 'reject'].includes(decision)) {
				return;
			}

			this.error = null;
			this.decisionRequestId = normalizedRequestId;

			try {
				const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
				const response = await fetch(`/api/requests/${normalizedRequestId}/decision`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-CSRF-TOKEN': csrfToken,
					},
					body: JSON.stringify({ decision }),
				});

				if (!response.ok) {
					const payload = await response.json().catch(() => ({}));
					throw new Error(payload?.message || 'Unable to submit decision.');
				}

				await this.load();
			} catch (error) {
				this.error = error instanceof Error ? error.message : 'Unable to submit decision.';
			} finally {
				this.decisionRequestId = null;
			}
		},

		async load() {
			const requestId = ++this.activeRequestId;
			this.loading = true;
			this.error = null;

			const isPolicyAndSecurityType = this.type.code === 7;
			const queryHours = this.window.queryHours;
			const baseRequestsEndpoint = `/api/dashboard/requests/${this.type.section}`;
			const requestsEndpoint = Number.isInteger(queryHours)
				? `${baseRequestsEndpoint}?hours=${queryHours}`
				: baseRequestsEndpoint;

			try {
				const [requestsResponse, policyStatsResponse] = await Promise.all([
					fetch(requestsEndpoint, { headers: { Accept: 'application/json' } }),
					isPolicyAndSecurityType
						? fetch('/api/policy-security/stats', { headers: { Accept: 'application/json' } })
						: Promise.resolve(null),
				]);

				if (!requestsResponse.ok || (policyStatsResponse && !policyStatsResponse.ok)) {
					throw new Error('Unable to load dashboard data.');
				}

				const requestsPayload = await requestsResponse.json();
				const policyStatsPayload = policyStatsResponse ? await policyStatsResponse.json() : null;

				if (requestId !== this.activeRequestId) {
					return;
				}

				const rawRequests = Array.isArray(requestsPayload?.data) ? requestsPayload.data : [];

				if (isPolicyAndSecurityType) {
					const statsData = policyStatsPayload && typeof policyStatsPayload.data === 'object'
						? policyStatsPayload.data
						: {};

					this.roleStats = {
						organizationNames: Array.isArray(statsData.organization_names) ? statsData.organization_names : [],
						organizationLabel: safeText(statsData.organization_label, 'Your Organizations'),
						adminsCount: Number.parseInt(String(statsData.admins_count ?? 0), 10) || 0,
						officersCount: Number.parseInt(String(statsData.officers_count ?? 0), 10) || 0,
						membershipsCount: Number.parseInt(String(statsData.memberships_count ?? 0), 10) || 0,
						pendingRoleChangeRequestsCount: Number.parseInt(String(statsData.pending_role_change_requests_count ?? 0), 10) || 0,
					};
				} else {
					this.roleStats = emptyRoleStats();
				}

				this.requests = rawRequests
					.map((requestItem) => {
						const approvalStatus = ['approved', 'rejected'].includes(requestItem?.approval_status)
							? requestItem.approval_status
							: 'pending';

						return {
							...requestItem,
							requester_name: buildRequesterName(requestItem.profile),
							name_or_title: safeText(requestItem?.name_or_title, this.type.singularLabel),
							request_organization: safeText(requestItem?.request_organization, 'Organization Pending'),
							requested_at_label: formatRelativeTimestamp(requestItem.requested_at),
							approval_status: approvalStatus,
							approval_status_label: getApprovalStatusLabel(approvalStatus),
						};
					})
					.sort((left, right) => {
						const leftTime = normalizeDate(left.requested_at)?.getTime() ?? 0;
						const rightTime = normalizeDate(right.requested_at)?.getTime() ?? 0;

						return rightTime - leftTime;
					});
				this.volumeSeries = buildVolumeSeries(this.requests, this.window.series);
				this.loaded = true;
			} catch (error) {
				if (requestId !== this.activeRequestId) {
					return;
				}

				this.requests = [];
				this.volumeSeries = buildVolumeSeries([], this.window.series);
				this.roleStats = emptyRoleStats();
				this.error = error instanceof Error ? error.message : 'Unable to load dashboard data.';
			} finally {
				if (requestId === this.activeRequestId) {
					this.loading = false;
				}
			}
		},

		init() {
			this.load();
		},
	});

	Alpine.store('dashboardData').init();
});
