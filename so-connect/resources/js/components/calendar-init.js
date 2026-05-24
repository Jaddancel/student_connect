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
    const eventRequestEndpoint =
        calendarWrapper.dataset.eventRequestEndpoint || "";
    const directRequestEndpoint =
        calendarWrapper.dataset.directRequestEndpoint || "";
    const officersEndpointTemplate =
        calendarWrapper.dataset.officersEndpoint ||
        "/api/organizations/{id}/officers";
    const lockedOrgIds = JSON.parse(
        calendarWrapper.dataset.lockedOrgIds || "[]",
    ).map(Number);
    const presidentName = calendarWrapper.dataset.presidentName || "";
    const presidentContact = calendarWrapper.dataset.presidentContact || "";
    const semesters = JSON.parse(calendarWrapper.dataset.semesters || "[]");
    const workplanStatuses = JSON.parse(
        calendarWrapper.dataset.workplanStatuses || "{}",
    );
    const todayStr =
        calendarWrapper.dataset.today ||
        new Date().toISOString().slice(0, 10);

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

    // Event Plan Drawer
    const eventPlanDrawer = document.getElementById("eventPlanDrawer");
    const eventPlanDrawerPanel = eventPlanDrawer?.querySelector(
        "[data-event-plan-drawer-panel]",
    );
    const planFeedbackEl = document.getElementById("event-plan-feedback");
    const eventPlanDrawerForm = document.getElementById("eventPlanDrawerForm");
    const planOrgEl = document.getElementById("plan-organization");
    const planTitleEl = document.getElementById("plan-title");
    const planTargetDateEl = document.getElementById("plan-target-date");
    const planResourcesEl = document.getElementById("plan-resources");
    const personsContainer = document.getElementById(
        "persons-responsible-container",
    );
    const submitPlanBtn = document.getElementById("submit-event-plan-btn");
    const drawerBackdrop = eventPlanDrawer?.querySelector(
        "[data-event-plan-drawer-backdrop]",
    );

    let currentPlanDate = "";
    let currentFormMode = "event-plan";
    let calendarInstance = null;
    let successDismissTimer = null;

    const pageSuccessAlert = document.getElementById("calendar-success-alert");
    const pageSuccessMessage = document.getElementById(
        "calendar-success-message",
    );
    const pageSuccessDismiss = document.getElementById(
        "calendar-success-dismiss",
    );

    const showPageSuccess = (message) => {
        if (!pageSuccessAlert || !pageSuccessMessage) return;
        if (successDismissTimer) clearTimeout(successDismissTimer);
        pageSuccessMessage.textContent = message;
        pageSuccessAlert.classList.remove("hidden");
        pageSuccessAlert.classList.add("flex");
        pageSuccessAlert.scrollIntoView({
            behavior: "smooth",
            block: "nearest",
        });
        successDismissTimer = setTimeout(hidePageSuccess, 5000);
    };

    const hidePageSuccess = () => {
        if (!pageSuccessAlert) return;
        pageSuccessAlert.classList.add("hidden");
        pageSuccessAlert.classList.remove("flex");
    };

    if (pageSuccessDismiss) {
        pageSuccessDismiss.addEventListener("click", hidePageSuccess);
    }

    // ─── Feedback helpers ───────────────────────────────────────────────────────

    const setPlanFeedback = (message, type = "error") => {
        if (!planFeedbackEl) return;

        planFeedbackEl.classList.remove(
            "hidden",
            "border-error-200",
            "bg-error-50",
            "text-error-700",
            "dark:border-error-500/20",
            "dark:bg-error-500/10",
            "dark:text-error-400",
            "border-success-200",
            "bg-success-50",
            "text-success-700",
            "dark:border-success-500/20",
            "dark:bg-success-500/10",
            "dark:text-success-400",
        );

        if (type === "success") {
            planFeedbackEl.classList.add(
                "border-success-200",
                "bg-success-50",
                "text-success-700",
                "dark:border-success-500/20",
                "dark:bg-success-500/10",
                "dark:text-success-400",
            );
        } else {
            planFeedbackEl.classList.add(
                "border-error-200",
                "bg-error-50",
                "text-error-700",
                "dark:border-error-500/20",
                "dark:bg-error-500/10",
                "dark:text-error-400",
            );
        }

        planFeedbackEl.textContent = message;
    };

    const clearPlanFeedback = () => {
        if (!planFeedbackEl) return;
        planFeedbackEl.textContent = "";
        planFeedbackEl.classList.add("hidden");
    };

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
              <div class="ep-item overflow-hidden rounded-lg border border-gray-100 dark:border-gray-700">
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

    // ─── Persons Responsible ─────────────────────────────────────────────────────

    const loadOfficers = async (organizationId) => {
        if (!personsContainer) return;

        personsContainer.innerHTML =
            '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Loading officers...</p>';

        try {
            const url = officersEndpointTemplate.replace(
                "{id}",
                organizationId,
            );
            const response = await fetch(url, {
                headers: { Accept: "application/json" },
            });

            if (!response.ok) throw new Error("Failed to load officers.");

            const officers = await response.json();

            if (!officers.length) {
                personsContainer.innerHTML =
                    '<p class="text-xs text-gray-400 dark:text-gray-500 italic">No officers found in this organization.</p>';
                return;
            }

            personsContainer.innerHTML = officers
                .map(
                    (o) => `
          <label class="flex items-center gap-2 py-1 cursor-pointer">
            <input type="checkbox" class="persons-checkbox rounded border-gray-300 dark:border-gray-600"
              name="persons_responsible[]" value="${o.user_id}" />
            <span class="text-sm text-gray-700 dark:text-gray-300">${o.name}</span>
          </label>`,
                )
                .join("");
        } catch {
            personsContainer.innerHTML =
                '<p class="text-xs text-red-400 italic">Could not load officers.</p>';
        }
    };

    const getSelectedPersons = () => {
        if (!personsContainer) return [];
        return Array.from(
            personsContainer.querySelectorAll(".persons-checkbox:checked"),
        ).map((el) => parseInt(el.value, 10));
    };

    const formatDateTimeLocal = (dateStr, timeStr) => {
        if (!dateStr) return "";
        return `${dateStr}T${timeStr}`;
    };

    const getFormState = () => {
        if (!window.Alpine || !eventPlanDrawerForm) return null;

        try {
            return window.Alpine.$data(eventPlanDrawerForm);
        } catch {
            return null;
        }
    };

    // ─── Event Plan Drawer ───────────────────────────────────────────────────────

    const resetPlanModal = () => {
        clearPlanFeedback();
        if (eventPlanDrawerForm) {
            eventPlanDrawerForm.reset();
        }

        const formState = getFormState();

        if (formState) {
            formState.facilities = ["", ""];
            formState.advisers = [""];
            formState.activityTypes = [];
            formState.activityTypeOther = "";
            formState.areaScope = "";
            formState.areaScopeOther = "";
            formState.sponsor = "";
            formState.sponsorOther = "";
            formState.extensionServices = "";
        }

        if (planOrgEl) planOrgEl.value = "";
        if (planTitleEl) planTitleEl.value = "";
        if (planTargetDateEl) planTargetDateEl.value = currentPlanDate || "";
        if (planResourcesEl) planResourcesEl.value = "";
        if (submitPlanBtn) submitPlanBtn.disabled = false;
        if (personsContainer) {
            personsContainer.innerHTML =
                '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an organization first.</p>';
        }

        const presidentNameEl = document.getElementById("president-name");
        const presidentContactEl = document.getElementById("president-contact");
        if (presidentNameEl && !presidentNameEl.value)
            presidentNameEl.value = presidentName;
        if (presidentContactEl && !presidentContactEl.value)
            presidentContactEl.value = presidentContact;
    };

    const openEventPlanDrawer = async (prefillDate) => {
        if (!eventPlanDrawer) return;

        closeDaySummaryModal();
        currentPlanDate = prefillDate || "";
        resetPlanModal();

        // Update drawer title and subtitle based on current form mode
        const drawerTitle = document.getElementById("event-plan-drawer-title");
        const drawerSubtitle = document.getElementById(
            "event-plan-drawer-subtitle",
        );
        if (currentFormMode === "activity-request") {
            if (drawerTitle)
                drawerTitle.textContent = "Request Activity";
            if (drawerSubtitle)
                drawerSubtitle.textContent =
                    "Submit a direct activity request to admin for approval.";
        } else {
            if (drawerTitle) drawerTitle.textContent = "Create Event Plan";
            if (drawerSubtitle)
                drawerSubtitle.textContent =
                    "Submit an event plan for your workplan.";
        }

        window.dispatchEvent(
            new CustomEvent("calendar-form-mode", {
                detail: { mode: currentFormMode },
            }),
        );

        if (planTargetDateEl && prefillDate) {
            planTargetDateEl.value = prefillDate;
        }

        const startTimeEl = document.getElementById("event-start-time");
        const endTimeEl = document.getElementById("event-end-time");
        if (startTimeEl && prefillDate) {
            startTimeEl.value = formatDateTimeLocal(prefillDate, "09:00");
        }
        if (endTimeEl && prefillDate) {
            endTimeEl.value = formatDateTimeLocal(prefillDate, "17:00");
        }

        const organizationValue = planOrgEl
            ? planOrgEl.value.trim()
            : eventPlanDrawerForm
                  ?.querySelector('input[name="organization_id"]')
                  ?.value.trim() || "";
        if (organizationValue) {
            await loadOfficers(organizationValue);
            if (lockedOrgIds.includes(parseInt(organizationValue, 10))) {
                setPlanFeedback(
                    "This organization's workplan for the current semester has been finalized. New event plans cannot be submitted until the next preparation period begins.",
                );
                if (submitPlanBtn) submitPlanBtn.disabled = true;
            }
        }

        eventPlanDrawer.classList.remove("pointer-events-none", "opacity-0");
        eventPlanDrawer.classList.add("pointer-events-auto", "opacity-100");
        if (eventPlanDrawerPanel) {
            eventPlanDrawerPanel.classList.remove("translate-x-full");
            eventPlanDrawerPanel.classList.add("translate-x-0");
        }
        document.body.style.overflow = "hidden";
    };

    const closeEventPlanDrawer = () => {
        if (!eventPlanDrawer) return;

        eventPlanDrawer.classList.add("pointer-events-none", "opacity-0");
        eventPlanDrawer.classList.remove("pointer-events-auto", "opacity-100");
        if (eventPlanDrawerPanel) {
            eventPlanDrawerPanel.classList.add("translate-x-full");
            eventPlanDrawerPanel.classList.remove("translate-x-0");
        }
        document.body.style.overflow = "";
    };

    const parseErrorMessage = async (response) => {
        const payload = await response.json().catch(() => ({}));

        if (payload?.errors && typeof payload.errors === "object") {
            const firstError = Object.values(payload.errors)
                .flat()
                .find((v) => typeof v === "string");
            if (firstError) return firstError;
        }

        return typeof payload?.message === "string" && payload.message.trim()
            ? payload.message
            : "Unable to submit event plan.";
    };

    const submitEventPlan = async (event) => {
        event.preventDefault();

        const isActivityRequest = currentFormMode === "activity-request";
        const endpoint = isActivityRequest
            ? directRequestEndpoint
            : eventRequestEndpoint;

        if (!canRequestEvent || !endpoint) {
            setPlanFeedback("You are not authorized to submit event plans.");
            return;
        }

        const form = eventPlanDrawerForm;
        if (!form) return;

        const organizationId = planOrgEl
            ? planOrgEl.value.trim()
            : form
                  .querySelector('input[name="organization_id"]')
                  ?.value.trim() || "";
        const title = planTitleEl ? planTitleEl.value.trim() : "";
        const targetDate = planTargetDateEl ? planTargetDateEl.value : "";
        const resourcesNeeded = planResourcesEl
            ? planResourcesEl.value.trim()
            : "";
        const personsResponsible = getSelectedPersons();

        if (
            !organizationId ||
            !title ||
            !targetDate ||
            (!isActivityRequest &&
                (!resourcesNeeded || personsResponsible.length === 0))
        ) {
            setPlanFeedback("Please fill in the required fields.");
            return;
        }

        if (!isActivityRequest && lockedOrgIds.includes(parseInt(organizationId, 10))) {
            setPlanFeedback(
                "This organization's workplan has been finalized. New event plans cannot be submitted until the next preparation period begins.",
            );
            return;
        }

        const originalLabel = submitPlanBtn ? submitPlanBtn.textContent : "";
        if (submitPlanBtn) {
            submitPlanBtn.disabled = true;
            submitPlanBtn.textContent = "Submitting...";
        }

        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content") || "";
            const formData = new FormData(form);
            formData.set("organization_id", organizationId);

            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: formData,
            });

            if (!response.ok) {
                const errorMessage = await parseErrorMessage(response);
                throw new Error(errorMessage);
            }

            const successText = isActivityRequest
                ? "Activity request submitted successfully. Awaiting admin approval."
                : "Event plan submitted successfully. It will appear in your workplan.";

            setPlanFeedback(successText, "success");
            window.setTimeout(() => {
                closeEventPlanDrawer();
                showPageSuccess(successText);
            }, 700);
        } catch (error) {
            const message =
                error instanceof Error && error.message
                    ? error.message
                    : isActivityRequest
                      ? "Unable to submit activity request."
                      : "Unable to submit event plan.";
            setPlanFeedback(message);
        } finally {
            if (submitPlanBtn) {
                submitPlanBtn.disabled = false;
                submitPlanBtn.textContent =
                    originalLabel ||
                    (isActivityRequest ? "Submit Request" : "Submit Event Plan");
            }
        }
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
            left: "prev,next",
            center: "title",
            right: "dayGridMonth,listWeek,timeGridDay",
        },
        events: "/api/events/calendar",
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
            const eventLevel =
                eventInfo.event.extendedProps?.calendar || "Primary";
            const colorClass = `fc-bg-${eventLevel.toLowerCase()}`;
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
            <div class="fc-day-event-card ${colorClass}">
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
          <div class="event-fc-color flex fc-event-main ${colorClass} p-1 rounded-sm">
            <div class="fc-daygrid-event-dot"></div>
            <div class="fc-event-time">${eventInfo.timeText}</div>
            <div class="fc-event-title">${eventInfo.event.title}</div>
          </div>`,
            };
        },
    });

    calendarInstance.render();

    // ─── Event listeners ─────────────────────────────────────────────────────────

    if (openEventPlanBtn) {
        openEventPlanBtn.addEventListener("click", () => {
            openEventPlanDrawer(currentPlanDate);
        });
    }

    if (eventPlanDrawerForm) {
        eventPlanDrawerForm.addEventListener("submit", submitEventPlan);
    }

    if (planOrgEl) {
        planOrgEl.addEventListener("change", () => {
            const orgId = planOrgEl.value;
            if (!orgId) {
                clearPlanFeedback();
                if (submitPlanBtn) submitPlanBtn.disabled = false;
                if (personsContainer)
                    personsContainer.innerHTML =
                        '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an organization first.</p>';
                return;
            }
            if (lockedOrgIds.includes(parseInt(orgId, 10))) {
                setPlanFeedback(
                    "This organization's workplan for the current semester has been finalized. New event plans cannot be submitted until the next preparation period begins.",
                );
                if (submitPlanBtn) submitPlanBtn.disabled = true;
                if (personsContainer)
                    personsContainer.innerHTML =
                        '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Submissions are locked.</p>';
            } else {
                clearPlanFeedback();
                if (submitPlanBtn) submitPlanBtn.disabled = false;
                loadOfficers(orgId);
            }
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

    if (planOrgEl) {
        const orgId = planOrgEl.value;
        if (orgId) {
            loadOfficers(orgId);
        }
    } else {
        const hiddenOrgId = eventPlanDrawerForm
            ?.querySelector('input[name="organization_id"]')
            ?.value.trim();
        if (hiddenOrgId) {
            loadOfficers(hiddenOrgId);
        }
    }
}

export default calendarInit;
