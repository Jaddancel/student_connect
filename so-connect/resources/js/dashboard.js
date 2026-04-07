const DASHBOARD_TYPES = {
	membership: {
		code: 1,
		label: 'Memberships',
		singularLabel: 'Membership Request',
	},
	events: {
		code: 2,
		label: 'Events',
		singularLabel: 'Event Request',
	},
	roles: {
		code: 3,
		label: 'Roles and Security',
		singularLabel: 'Role and Security Request',
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
		volumeLegendLabel: 'Requests per hour',
		peakVolumeSuffix: '/hour',
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
		volumeLegendLabel: 'Requests per day',
		peakVolumeSuffix: '/day',
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
		volumeLegendLabel: 'Requests per month',
		peakVolumeSuffix: '/month',
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

function formatActionSummary(action, typeCode) {
	const parts = safeText(action, '').split('|').map((part) => part.trim()).filter(Boolean);

	if (typeCode === 1) {
		return {
			headline: 'Membership Request',
			detail: parts[0] ? `Organization #${parts[0]}` : 'Organization Pending',
		};
	}

	if (typeCode === 2) {
		if (parts.length >= 6) {
			return {
				headline: safeText(parts[2], 'Event Request'),
				detail: safeText(parts[5], 'No event description provided.'),
			};
		}

		if (parts.length >= 5) {
			return {
				headline: safeText(parts[0], 'Event Request'),
				detail: safeText(parts[1], 'No event description provided.'),
			};
		}

		return {
			headline: 'Event Request',
			detail: safeText(action, 'No event details available.'),
		};
	}

	return {
		headline: DASHBOARD_TYPES.roles.singularLabel,
		detail: safeText(action, 'No details available.'),
	};
}

function extractOrganizationId(action) {
	const firstPart = safeText(action, '').split('|')[0]?.trim() ?? '';
	const organizationId = Number.parseInt(firstPart, 10);

	if (!Number.isInteger(organizationId) || organizationId <= 0) {
		return null;
	}

	return organizationId;
}

function formatListColumns(requestItem, typeCode, organizationNameMap = {}) {
	const parts = safeText(requestItem?.action, '').split('|').map((part) => part.trim()).filter(Boolean);
	const requesterName = buildRequesterName(requestItem?.profile);
	const requesterId = safeText(requestItem?.user, 'Unknown');
	const organizationId = extractOrganizationId(requestItem?.action);
	const organizationName = organizationId
		? safeText(organizationNameMap[String(organizationId)], `Organization #${organizationId}`)
		: null;

	if (typeCode === 1) {
		return {
			nameOrTitle: requesterName === 'Unknown Requester' ? `User #${requesterId}` : requesterName,
			organization: organizationName ?? 'Organization Pending',
		};
	}

	if (typeCode === 2) {
		const eventTitle = parts.length >= 3
			? safeText(parts[2], 'Event Request')
			: safeText(parts[0], 'Event Request');

		return {
			nameOrTitle: eventTitle,
			organization: organizationName ?? 'Organization Unknown',
		};
	}

	return {
		nameOrTitle: requesterName === 'Unknown Requester' ? `User #${requesterId}` : requesterName,
		organization: 'N/A',
	};
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

function buildMonthlySeries(requests) {
	const now = new Date();
	const currentMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
	const buckets = [];

	const parsedTimes = requests
		.map((requestItem) => normalizeDate(requestItem.requested_at))
		.filter((dateValue) => dateValue instanceof Date);

	const earliest = parsedTimes.length
		? parsedTimes.reduce((minValue, currentValue) => (currentValue < minValue ? currentValue : minValue), parsedTimes[0])
		: currentMonthStart;

	const startMonth = new Date(earliest.getFullYear(), earliest.getMonth(), 1);
	const cursor = new Date(startMonth);

	while (cursor <= currentMonthStart) {
		const key = `${cursor.getFullYear()}-${cursor.getMonth()}`;

		buckets.push({
			key,
			label: cursor.toLocaleDateString(undefined, {
				month: 'short',
				year: '2-digit',
			}),
			count: 0,
		});

		cursor.setMonth(cursor.getMonth() + 1);
	}

	const bucketByKey = new Map(buckets.map((bucket) => [bucket.key, bucket]));

	for (const request of requests) {
		const at = normalizeDate(request.requested_at);

		if (!at) {
			continue;
		}

		const key = `${at.getFullYear()}-${at.getMonth()}`;
		const bucket = bucketByKey.get(key);

		if (bucket) {
			bucket.count += 1;
		}
	}

	return buckets;
}

function buildVolumeSeries(requests, windowType) {
	if (windowType === 'daily') {
		return buildDailySeries(requests, 30);
	}

	if (windowType === 'monthly') {
		return buildMonthlySeries(requests);
	}

	return buildHourlySeries(requests, 24);
}

function getApprovalStatus(approvalItem) {
	if (!approvalItem || typeof approvalItem !== 'object') {
		return 'pending';
	}

	if (approvalItem.status === false) {
		return 'approved';
	}

	if (approvalItem.status === true) {
		return 'rejected';
	}

	return 'pending';
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
		approvals: [],

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

		get volumeLegendLabel() {
			return this.window.volumeLegendLabel;
		},

		get peakVolumeSuffix() {
			return this.window.peakVolumeSuffix;
		},

		get totalRequests() {
			return this.requests.length;
		},

		get recentRequests() {
			return this.requests.slice(0, RECENT_LIMIT);
		},

		get approvedCount() {
			return this.approvals.filter((approval) => approval?.status === false).length;
		},

		get deniedCount() {
			return this.approvals.filter((approval) => approval?.status === true).length;
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

		get volumeSeries() {
			return buildVolumeSeries(this.requests, this.window.series);
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

			const actionTypeCode = this.type.code;
			const queryHours = this.window.queryHours;
			const requestsEndpoint = Number.isInteger(queryHours)
				? `/api/requests/${actionTypeCode}?hours=${queryHours}`
				: `/api/requests/${actionTypeCode}`;

			try {
				const [requestsResponse, approvalsResponse] = await Promise.all([
					fetch(requestsEndpoint),
					fetch(`/api/approvals/${actionTypeCode}`),
				]);

				if (!requestsResponse.ok || !approvalsResponse.ok) {
					throw new Error('Unable to load dashboard data.');
				}

				const requestsPayload = await requestsResponse.json();
				const approvalsPayload = await approvalsResponse.json();

				if (requestId !== this.activeRequestId) {
					return;
				}

				const rawRequests = Array.isArray(requestsPayload?.data) ? requestsPayload.data : [];
				const rawApprovals = Array.isArray(approvalsPayload?.data) ? approvalsPayload.data : [];
				const organizationIds = Array.from(new Set(rawRequests
					.map((requestItem) => extractOrganizationId(requestItem.action))
					.filter((organizationId) => organizationId !== null)));
				let organizationNameMap = {};

				if (organizationIds.length) {
					try {
						const organizationResponse = await fetch(`/api/organizations?ids=${organizationIds.join(',')}`);

						if (organizationResponse.ok) {
							const organizationsPayload = await organizationResponse.json();
							organizationNameMap = organizationsPayload && typeof organizationsPayload.data === 'object'
								? organizationsPayload.data
								: {};
						}
					} catch (organizationError) {
						console.warn('Unable to resolve organization names for dashboard list.', organizationError);
					}
				}

				this.requests = rawRequests
					.map((requestItem) => {
						const summary = formatActionSummary(requestItem.action, actionTypeCode);
						const listColumns = formatListColumns(requestItem, actionTypeCode, organizationNameMap);

						return {
							...requestItem,
							requester_name: buildRequesterName(requestItem.profile),
							action_headline: summary.headline,
							action_detail: summary.detail,
							name_or_title: listColumns.nameOrTitle,
							request_organization: listColumns.organization,
							requested_at_label: formatRelativeTimestamp(requestItem.requested_at),
						};
					})
					.sort((left, right) => {
						const leftTime = normalizeDate(left.requested_at)?.getTime() ?? 0;
						const rightTime = normalizeDate(right.requested_at)?.getTime() ?? 0;

						return rightTime - leftTime;
					});

				const requestIds = new Set(this.requests
					.map((requestItem) => Number.parseInt(String(requestItem.request_id), 10))
					.filter((requestItemId) => Number.isInteger(requestItemId) && requestItemId > 0));

				this.approvals = rawApprovals.filter((approvalItem) => {
					const linkedRequestId = Number.parseInt(String(approvalItem?.request?.request_id ?? ''), 10);

					return requestIds.has(linkedRequestId);
				});

				const approvalByRequestId = new Map(this.approvals
					.map((approvalItem) => [Number.parseInt(String(approvalItem?.request?.request_id ?? ''), 10), approvalItem])
					.filter(([linkedRequestId]) => Number.isInteger(linkedRequestId) && linkedRequestId > 0));

				this.requests = this.requests.map((requestItem) => {
					const requestItemId = Number.parseInt(String(requestItem.request_id), 10);
					const approvalItem = approvalByRequestId.get(requestItemId) ?? null;
					const approvalStatus = getApprovalStatus(approvalItem);

					return {
						...requestItem,
						approval_status: approvalStatus,
						approval_status_label: getApprovalStatusLabel(approvalStatus),
					};
				});
				this.loaded = true;
			} catch (error) {
				if (requestId !== this.activeRequestId) {
					return;
				}

				this.requests = [];
				this.approvals = [];
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
