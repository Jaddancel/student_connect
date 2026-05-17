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
  const eventRequestEndpoint = calendarWrapper.dataset.eventRequestEndpoint || "";
  const officersEndpointTemplate = calendarWrapper.dataset.officersEndpoint || "/api/organizations/{id}/officers";

  // Day Summary Modal
  const daySummaryModal = document.getElementById("daySummaryModal");
  const daySummaryDateEl = document.getElementById("daySummaryDate");
  const daySummaryEventListEl = document.getElementById("daySummaryEventList");
  const openEventPlanBtn = document.getElementById("open-event-plan-btn");

  // Event Plan Modal
  const eventPlanModal = document.getElementById("eventPlanModal");
  const planFeedbackEl = document.getElementById("event-plan-feedback");
  const planOrgEl = document.getElementById("plan-organization");
  const planTitleEl = document.getElementById("plan-title");
  const planTargetDateEl = document.getElementById("plan-target-date");
  const planResourcesEl = document.getElementById("plan-resources");
  const personsContainer = document.getElementById("persons-responsible-container");
  const submitPlanBtn = document.getElementById("submit-event-plan-btn");

  let currentPlanDate = "";
  let calendarInstance = null;

  // ─── Feedback helpers ───────────────────────────────────────────────────────

  const setPlanFeedback = (message, type = "error") => {
    if (!planFeedbackEl) return;

    planFeedbackEl.classList.remove(
      "hidden",
      "border-error-200", "bg-error-50", "text-error-700",
      "dark:border-error-500/20", "dark:bg-error-500/10", "dark:text-error-400",
      "border-success-200", "bg-success-50", "text-success-700",
      "dark:border-success-500/20", "dark:bg-success-500/10", "dark:text-success-400"
    );

    if (type === "success") {
      planFeedbackEl.classList.add(
        "border-success-200", "bg-success-50", "text-success-700",
        "dark:border-success-500/20", "dark:bg-success-500/10", "dark:text-success-400"
      );
    } else {
      planFeedbackEl.classList.add(
        "border-error-200", "bg-error-50", "text-error-700",
        "dark:border-error-500/20", "dark:bg-error-500/10", "dark:text-error-400"
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
        daySummaryEventListEl.innerHTML = eventsOnDay
          .map((ev) => {
            const startStr = ev.start
              ? new Date(ev.start).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
              : "";
            const org = ev.extendedProps?.organization || "";
            return `
              <div class="flex items-start gap-2 rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-700">
                <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></div>
                <div>
                  <p class="text-sm font-medium text-gray-800 dark:text-white/90">${ev.title}</p>
                  ${startStr ? `<p class="text-xs text-gray-500 dark:text-gray-400">${startStr}${org ? " · " + org : ""}</p>` : ""}
                </div>
              </div>`;
          })
          .join("");
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
      const url = officersEndpointTemplate.replace("{id}", organizationId);
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
              name="persons_responsible" value="${o.user_id}" />
            <span class="text-sm text-gray-700 dark:text-gray-300">${o.name}</span>
          </label>`
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
      personsContainer.querySelectorAll(".persons-checkbox:checked")
    ).map((el) => parseInt(el.value, 10));
  };

  // ─── Event Plan Modal ────────────────────────────────────────────────────────

  const resetPlanModal = () => {
    clearPlanFeedback();
    if (planOrgEl) planOrgEl.value = "";
    if (planTitleEl) planTitleEl.value = "";
    if (planTargetDateEl) planTargetDateEl.value = currentPlanDate || "";
    if (planResourcesEl) planResourcesEl.value = "";
    if (personsContainer)
      personsContainer.innerHTML =
        '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an organization first.</p>';
  };

  const openEventPlanModal = (prefillDate) => {
    if (!eventPlanModal) return;

    closeDaySummaryModal();
    currentPlanDate = prefillDate || "";
    resetPlanModal();

    if (planTargetDateEl && prefillDate) {
      planTargetDateEl.value = prefillDate;
    }

    eventPlanModal.style.display = "flex";
    document.body.style.overflow = "hidden";
  };

  const closeEventPlanModal = () => {
    if (!eventPlanModal) return;
    eventPlanModal.style.display = "none";
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

  const submitEventPlan = async () => {
    if (!canRequestEvent || !eventRequestEndpoint) {
      setPlanFeedback("You are not authorized to submit event plans.");
      return;
    }

    const organizationId = planOrgEl ? planOrgEl.value.trim() : "";
    const title = planTitleEl ? planTitleEl.value.trim() : "";
    const targetDate = planTargetDateEl ? planTargetDateEl.value : "";
    const resourcesNeeded = planResourcesEl ? planResourcesEl.value.trim() : "";
    const personsResponsible = getSelectedPersons();

    if (!organizationId || !title || !targetDate) {
      setPlanFeedback("Please fill in Organization, Title, and Target Date.");
      return;
    }

    const originalLabel = submitPlanBtn ? submitPlanBtn.textContent : "";
    if (submitPlanBtn) {
      submitPlanBtn.disabled = true;
      submitPlanBtn.textContent = "Submitting...";
    }

    try {
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

      const response = await fetch(eventRequestEndpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          organization_id: parseInt(organizationId, 10),
          title,
          target_date: targetDate,
          resources_needed: resourcesNeeded || null,
          persons_responsible: personsResponsible,
        }),
      });

      if (!response.ok) {
        const errorMessage = await parseErrorMessage(response);
        throw new Error(errorMessage);
      }

      setPlanFeedback("Event plan submitted successfully.", "success");
      window.setTimeout(() => {
        closeEventPlanModal();
      }, 700);
    } catch (error) {
      const message =
        error instanceof Error && error.message
          ? error.message
          : "Unable to submit event plan.";
      setPlanFeedback(message);
    } finally {
      if (submitPlanBtn) {
        submitPlanBtn.disabled = false;
        submitPlanBtn.textContent = originalLabel || "Submit Event Plan";
      }
    }
  };

  // ─── Calendar setup ──────────────────────────────────────────────────────────

  const newDate = new Date();
  const getDynamicMonth = () => {
    const month = newDate.getMonth() + 1;
    return month < 10 ? `0${month}` : `${month}`;
  };

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
    select(info) {
      const allEvents = calendarInstance ? calendarInstance.getEvents() : [];
      const clickedDate = info.startStr.slice(0, 10);

      const eventsOnDay = allEvents.filter((ev) => {
        const evStart = ev.startStr ? ev.startStr.slice(0, 10) : "";
        const evEnd = ev.endStr ? ev.endStr.slice(0, 10) : evStart;
        return evStart <= clickedDate && (evEnd > clickedDate || evStart === clickedDate);
      });

      openDaySummaryModal(clickedDate, eventsOnDay);
    },
    displayEventTime: false,
    eventContent(eventInfo) {
      const eventLevel = eventInfo.event.extendedProps?.calendar || "Primary";
      const colorClass = `fc-bg-${eventLevel.toLowerCase()}`;

      return {
        html: `
            <div class="event-fc-color flex fc-event-main ${colorClass} p-1 rounded-sm">
              <div class="fc-daygrid-event-dot"></div>
              <div class="fc-event-time">${eventInfo.timeText}</div>
              <div class="fc-event-title">${eventInfo.event.title}</div>
            </div>
          `,
      };
    },
  });

  calendarInstance.render();

  // ─── Event listeners ─────────────────────────────────────────────────────────

  if (openEventPlanBtn) {
    openEventPlanBtn.addEventListener("click", () => {
      openEventPlanModal(currentPlanDate);
    });
  }

  if (submitPlanBtn) {
    submitPlanBtn.addEventListener("click", submitEventPlan);
  }

  if (planOrgEl) {
    planOrgEl.addEventListener("change", () => {
      const orgId = planOrgEl.value;
      if (orgId) {
        loadOfficers(orgId);
      } else if (personsContainer) {
        personsContainer.innerHTML =
          '<p class="text-xs text-gray-400 dark:text-gray-500 italic">Select an organization first.</p>';
      }
    });
  }

  document.querySelectorAll(".day-summary-close, .modal-close-btn").forEach((btn) => {
    btn.addEventListener("click", closeDaySummaryModal);
  });

  document.querySelectorAll(".event-plan-close").forEach((btn) => {
    btn.addEventListener("click", closeEventPlanModal);
  });

  window.addEventListener("click", (event) => {
    if (event.target === daySummaryModal) closeDaySummaryModal();
    if (event.target === eventPlanModal) closeEventPlanModal();
  });
}

export default calendarInit;
