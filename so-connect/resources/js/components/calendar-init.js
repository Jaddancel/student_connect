import { Calendar } from "@fullcalendar/core";
import dayGridPlugin from "@fullcalendar/daygrid";
import listPlugin from "@fullcalendar/list";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";

export function calendarInit() {
    const calendarWrapper = document.querySelector("#calendar");

    if (!calendarWrapper) {
        return;
    }

    const canRequestEvent = calendarWrapper.dataset.canRequestEvent === "1";
    const semesters = JSON.parse(calendarWrapper.dataset.semesters || "[]");
    const workplanStatuses = JSON.parse(
        calendarWrapper.dataset.workplanStatuses || "{}",
    );
    const todayStr =
        calendarWrapper.dataset.today || new Date().toISOString().slice(0, 10);

    // Calendar scope: "current" = the org selected in the session switcher,
    // "all" = every org the user belongs to. Toggled from the header.
    let eventScope =
        calendarWrapper.dataset.eventScope === "all" ? "all" : "current";

    const updateScopeButtons = () => {
        const currentBtn = calendarWrapper.querySelector(
            ".fc-scopeCurrent-button",
        );
        const allBtn = calendarWrapper.querySelector(".fc-scopeAll-button");
        if (currentBtn)
            currentBtn.classList.toggle(
                "fc-button-active",
                eventScope === "current",
            );
        if (allBtn)
            allBtn.classList.toggle("fc-button-active", eventScope === "all");
    };

    const setEventScope = (scope) => {
        if (eventScope === scope) return;
        eventScope = scope;
        updateScopeButtons();
        if (calendarInstance) calendarInstance.refetchEvents();
    };

    // ─── Semester detection helpers ──────────────────────────────────────────────

    const getSemesterForDate = (dateStr) => {
        const sorted = [...semesters].sort((a, b) =>
            a.starts_at.localeCompare(b.starts_at),
        );
        let found = null;
        for (const s of sorted) {
            if (s.starts_at <= dateStr) found = s;
            else break;
        }
        return found;
    };

    const getFormModeForDate = (dateStr) => {
        if (dateStr < todayStr) return "disabled";
        const semester = getSemesterForDate(dateStr);
        // No semester started on or before this date → treat as future planning
        if (!semester) return "event-plan";
        // Future semester (not yet started) → event plan goes into workplan
        if (semester.starts_at > todayStr) return "event-plan";
        // Semester has started → use activity request form
        return "activity-request";
    };

    // Day Summary Modal
    const daySummaryModal = document.getElementById("daySummaryModal");
    const daySummaryDateEl = document.getElementById("daySummaryDate");
    const daySummaryEventListEl = document.getElementById(
        "daySummaryEventList",
    );
    const openEventPlanBtn = document.getElementById("open-event-plan-btn");

    // Event Plan Modal — embeds the new_event builder form, which submits
    // itself to the generic form renderer; this JS only opens/closes it.
    const eventPlanDrawer = document.getElementById("eventPlanDrawer");
    const drawerBackdrop = eventPlanDrawer?.querySelector(
        "[data-event-plan-drawer-backdrop]",
    );

    let currentPlanDate = "";
    let currentFormMode = "event-plan";
    let calendarInstance = null;

    // ─── Day Summary Modal ───────────────────────────────────────────────────────

    const openDaySummaryModal = (dateStr, eventsOnDay) => {
        if (!daySummaryModal) return;

        currentPlanDate = dateStr;

        if (daySummaryDateEl) {
            const d = new Date(dateStr + "T00:00:00");
            daySummaryDateEl.textContent = d.toLocaleDateString(undefined, {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        }

        if (daySummaryEventListEl) {
            if (eventsOnDay.length === 0) {
                daySummaryEventListEl.innerHTML =
                    '<p class="text-sm text-gray-400 dark:text-gray-500 italic">No events on this day.</p>';
            } else {
                const chevronSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`;

                daySummaryEventListEl.innerHTML = eventsOnDay
                    .map((ev) => {
                        const startStr = ev.start
                            ? new Date(ev.start).toLocaleTimeString(undefined, {
                                  hour: "2-digit",
                                  minute: "2-digit",
                              })
                            : "";
                        const endStr = ev.end
                            ? new Date(ev.end).toLocaleTimeString(undefined, {
                                  hour: "2-digit",
                                  minute: "2-digit",
                              })
                            : "";
                        const org = ev.extendedProps?.organization || "";
                        const location = ev.extendedProps?.location || "";
                        const description = ev.extendedProps?.description || "";
                        const hue = Number(ev.extendedProps?.orgHue);
                        const accentHue = Number.isFinite(hue) ? hue : 140;
                        const timeRange =
                            startStr && endStr
                                ? `${startStr} – ${endStr}`
                                : startStr;
                        const metaLine = [timeRange, org]
                            .filter(Boolean)
                            .join(" · ");

                        const detailRows = [
                            location
                                ? `<div class="flex gap-1.5"><span class="shrink-0 font-medium text-gray-600 dark:text-gray-300">Location</span><span class="text-gray-500 dark:text-gray-400">${location}</span></div>`
                                : "",
                            timeRange
                                ? `<div class="flex gap-1.5"><span class="shrink-0 font-medium text-gray-600 dark:text-gray-300">Time</span><span class="text-gray-500 dark:text-gray-400">${timeRange}</span></div>`
                                : "",
                            org
                                ? `<div class="flex gap-1.5"><span class="shrink-0 font-medium text-gray-600 dark:text-gray-300">Organization</span><span class="text-gray-500 dark:text-gray-400">${org}</span></div>`
                                : "",
                            description
                                ? `<div class="flex gap-1.5"><span class="shrink-0 font-medium text-gray-600 dark:text-gray-300">Details</span><span class="text-gray-500 dark:text-gray-400">${description}</span></div>`
                                : "",
                        ]
                            .filter(Boolean)
                            .join("");

                        return `
              <div class="ep-item overflow-hidden rounded-lg border border-gray-100 dark:border-gray-700" style="border-left: 4px solid hsl(${accentHue} 70% 45%);">
                <button type="button" class="ep-toggle w-full flex items-center gap-2.5 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                  <span class="ep-chevron flex h-4 w-4 shrink-0 items-center justify-center text-gray-400 dark:text-gray-500 transition-transform duration-200">${chevronSvg}</span>
                  <span class="flex-1 min-w-0">
                    <span class="block text-sm font-semibold text-gray-800 dark:text-white/90 truncate">${ev.title}</span>
                    ${metaLine ? `<span class="block text-xs text-gray-500 dark:text-gray-400">${metaLine}</span>` : ""}
                  </span>
                </button>
                <div class="ep-detail" style="max-height:0;overflow:hidden;transition:max-height 0.25s ease;">
                  <div class="border-t border-gray-100 dark:border-gray-700 px-3 py-2.5 space-y-1 text-xs">
                    ${detailRows || '<span class="text-gray-400 dark:text-gray-500 italic">No additional details.</span>'}
                  </div>
                </div>
              </div>`;
                    })
                    .join("");

                daySummaryEventListEl
                    .querySelectorAll(".ep-toggle")
                    .forEach((btn) => {
                        btn.addEventListener("click", () => {
                            const item = btn.closest(".ep-item");
                            const detail = item.querySelector(".ep-detail");
                            const chevron = btn.querySelector(".ep-chevron");
                            const isOpen =
                                detail.style.maxHeight !== "0px" &&
                                detail.style.maxHeight !== "";

                            daySummaryEventListEl
                                .querySelectorAll(".ep-detail")
                                .forEach((d) => {
                                    d.style.maxHeight = "0px";
                                });
                            daySummaryEventListEl
                                .querySelectorAll(".ep-chevron")
                                .forEach((c) => {
                                    c.style.transform = "";
                                });

                            if (!isOpen) {
                                detail.style.maxHeight =
                                    detail.scrollHeight + "px";
                                chevron.style.transform = "rotate(90deg)";
                            }
                        });
                    });
            }
        }

        // Auto-toggle create button based on date context
        if (canRequestEvent && openEventPlanBtn) {
            const mode = getFormModeForDate(dateStr);
            currentFormMode = mode;
            openEventPlanBtn.dataset.createMode = mode;
            if (mode === "disabled") {
                openEventPlanBtn.disabled = true;
                openEventPlanBtn.textContent = "Past Date";
            } else if (mode === "activity-request") {
                openEventPlanBtn.disabled = false;
                openEventPlanBtn.textContent = "Request Activity";
            } else {
                openEventPlanBtn.disabled = false;
                openEventPlanBtn.textContent = "Create Event Plan";
            }
        }

        daySummaryModal.style.display = "flex";
        document.body.style.overflow = "hidden";
    };

    const closeDaySummaryModal = () => {
        if (!daySummaryModal) return;
        daySummaryModal.style.display = "none";
        document.body.style.overflow = "";
    };

    // ─── Event Plan Modal ────────────────────────────────────────────────────────

    const openEventPlanDrawer = (prefillDate) => {
        if (!eventPlanDrawer) return;

        closeDaySummaryModal();
        currentPlanDate = prefillDate || "";

        // The drawer embeds the new_event builder form; prefill its target_date
        // field (a flatpickr text input) with the clicked day.
        if (prefillDate) {
            const dateInput = eventPlanDrawer.querySelector(
                'input[name="target_date"]',
            );
            if (dateInput) {
                if (dateInput._flatpickr) {
                    dateInput._flatpickr.setDate(prefillDate, true);
                } else {
                    dateInput.value = prefillDate;
                }
            }
        }

        eventPlanDrawer.classList.remove("pointer-events-none", "opacity-0");
        eventPlanDrawer.classList.add("pointer-events-auto", "opacity-100");
        document.body.style.overflow = "hidden";
    };

    const closeEventPlanDrawer = () => {
        if (!eventPlanDrawer) return;

        eventPlanDrawer.classList.add("pointer-events-none", "opacity-0");
        eventPlanDrawer.classList.remove("pointer-events-auto", "opacity-100");
        document.body.style.overflow = "";
    };

    // ─── Calendar setup ──────────────────────────────────────────────────────────

    const newDate = new Date();
    const getDynamicMonth = () => {
        const month = newDate.getMonth() + 1;
        return month < 10 ? `0${month}` : `${month}`;
    };

    const fmtTime = (dt) =>
        dt
            ? new Date(dt).toLocaleTimeString(undefined, {
                  hour: "2-digit",
                  minute: "2-digit",
              })
            : "";

    calendarInstance = new Calendar(calendarWrapper, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        selectable: true,
        dayMaxEvents: 3,
        initialView: "dayGridMonth",
        initialDate: `${newDate.getFullYear()}-${getDynamicMonth()}-07`,
        headerToolbar: {
            left: "prev,next scopeCurrent,scopeAll",
            center: "title",
            right: "dayGridMonth,listWeek,timeGridDay",
        },
        customButtons: {
            scopeCurrent: {
                text: "CURRENT ORG",
                click: () => setEventScope("current"),
            },
            scopeAll: {
                text: "ALL",
                click: () => setEventScope("all"),
            },
        },
        events: {
            url: "/api/events/calendar",
            extraParams: () => ({ scope: eventScope }),
        },
        nowIndicator: true,
        slotDuration: "00:30:00",
        slotMinTime: "06:00:00",
        slotMaxTime: "23:00:00",
        scrollTime: "07:00:00",
        select(info) {
            const allEvents = calendarInstance
                ? calendarInstance.getEvents()
                : [];
            const clickedDate = info.startStr.slice(0, 10);

            const eventsOnDay = allEvents.filter((ev) => {
                const evStart = ev.startStr ? ev.startStr.slice(0, 10) : "";
                const evEnd = ev.endStr ? ev.endStr.slice(0, 10) : evStart;
                return (
                    evStart <= clickedDate &&
                    (evEnd > clickedDate || evStart === clickedDate)
                );
            });

            openDaySummaryModal(clickedDate, eventsOnDay);
        },
        displayEventTime: false,
        dayHeaderContent(arg) {
            if (arg.view.type === "timeGridDay") {
                const weekday = arg.date.toLocaleDateString(undefined, {
                    weekday: "long",
                });
                const day = arg.date.getDate();
                const isToday = arg.isToday;
                return {
                    html: `
            <div class="fc-day-header-wrap${isToday ? " fc-day-header-today" : ""}">
              <span class="fc-day-header-weekday">${weekday}</span>
              <span class="fc-day-header-num">${day}</span>
            </div>`,
                };
            }
            return arg.text;
        },
        eventContent(eventInfo) {
            const hue = Number(eventInfo.event.extendedProps?.orgHue);
            const colorClass = "fc-org-color";
            const colorStyle = `style="--org-hue: ${Number.isFinite(hue) ? hue : 140}"`;
            const viewType = eventInfo.view.type;

            if (viewType === "timeGridDay") {
                const org = eventInfo.event.extendedProps?.organization || "";
                const location = eventInfo.event.extendedProps?.location || "";
                const start = fmtTime(eventInfo.event.start);
                const end = fmtTime(eventInfo.event.end);
                const timeStr = start && end ? `${start} – ${end}` : start;
                const meta = [location, org].filter(Boolean).join(" · ");

                return {
                    html: `
            <div class="fc-day-event-card ${colorClass}" ${colorStyle}>
              <div class="fc-day-event-accent"></div>
              <div class="fc-day-event-body">
                <div class="fc-day-event-title">${eventInfo.event.title}</div>
                ${timeStr ? `<div class="fc-day-event-time">${timeStr}</div>` : ""}
                ${meta ? `<div class="fc-day-event-meta">${meta}</div>` : ""}
              </div>
            </div>`,
                };
            }

            return {
                html: `
          <div class="event-fc-color flex fc-event-main ${colorClass} p-1 rounded-sm" ${colorStyle}>
            <div class="fc-daygrid-event-dot"></div>
            <div class="fc-event-time">${eventInfo.timeText}</div>
            <div class="fc-event-title">${eventInfo.event.title}</div>
          </div>`,
            };
        },
    });

    // Re-apply the active scope highlight whenever the toolbar re-renders.
    calendarInstance.setOption("datesSet", () => updateScopeButtons());

    calendarInstance.render();
    updateScopeButtons();

    // ─── Event listeners ─────────────────────────────────────────────────────────

    if (openEventPlanBtn) {
        openEventPlanBtn.addEventListener("click", () => {
            openEventPlanDrawer(currentPlanDate);
        });
    }

    document
        .querySelectorAll(".day-summary-close, .modal-close-btn")
        .forEach((btn) => {
            btn.addEventListener("click", closeDaySummaryModal);
        });

    document.querySelectorAll(".event-plan-close").forEach((btn) => {
        btn.addEventListener("click", closeEventPlanDrawer);
    });

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener("click", closeEventPlanDrawer);
    }

    // A rejected/invalid submission redirects back here with flashed errors;
    // the drawer starts closed, so it must reopen itself to surface them —
    // otherwise the alert renders invisibly behind a closed drawer.
    if (eventPlanDrawer?.dataset.hasErrors === "1") {
        openEventPlanDrawer(currentPlanDate);
    }
}

export default calendarInit;
