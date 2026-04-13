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
  const modalEl = document.getElementById("eventModal");

  const modalHeaderEl = document.querySelector("#eventModalLabel");
  const feedbackEl = document.querySelector("#event-form-feedback");
  const organizationEl = document.querySelector("#event-organization");
  const titleEl = document.querySelector("#event-title");
  const locationEl = document.querySelector("#event-location");
  const descriptionEl = document.querySelector("#event-description");
  const startDateEl = document.querySelector("#event-start-date");
  const endDateEl = document.querySelector("#event-end-date");
  const addBtnEl = document.querySelector(".btn-add-event");
  const openModalBtnEl = document.querySelector("#open-event-request-modal");

  const newDate = new Date();
  const getDynamicMonth = () => {
    const month = newDate.getMonth() + 1;
    return month < 10 ? `0${month}` : `${month}`;
  };

  const setFeedback = (message, type = "error") => {
    if (!feedbackEl) {
      return;
    }

    feedbackEl.classList.remove(
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
      "dark:text-success-400"
    );

    if (type === "success") {
      feedbackEl.classList.add(
        "border-success-200",
        "bg-success-50",
        "text-success-700",
        "dark:border-success-500/20",
        "dark:bg-success-500/10",
        "dark:text-success-400"
      );
    } else {
      feedbackEl.classList.add(
        "border-error-200",
        "bg-error-50",
        "text-error-700",
        "dark:border-error-500/20",
        "dark:bg-error-500/10",
        "dark:text-error-400"
      );
    }

    feedbackEl.textContent = message;
  };

  const clearFeedback = () => {
    if (!feedbackEl) {
      return;
    }

    feedbackEl.textContent = "";
    feedbackEl.classList.add("hidden");
  };

  const toLocalDatetimeInput = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }

    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return localDate.toISOString().slice(0, 16);
  };

  const applyDefaultDateRange = (baseDate = new Date()) => {
    if (!(startDateEl && endDateEl)) {
      return;
    }

    const startDate = new Date(baseDate);
    startDate.setHours(9, 0, 0, 0);

    const endDate = new Date(startDate);
    endDate.setHours(startDate.getHours() + 1);

    startDateEl.value = toLocalDatetimeInput(startDate);
    endDateEl.value = toLocalDatetimeInput(endDate);
  };

  const resetModalFields = () => {
    clearFeedback();

    if (organizationEl) {
      organizationEl.value = "";
    }
    if (titleEl) {
      titleEl.value = "";
    }
    if (locationEl) {
      locationEl.value = "";
    }
    if (descriptionEl) {
      descriptionEl.value = "";
    }

    applyDefaultDateRange(new Date());
  };

  const openModalForRequest = (baseDate = new Date()) => {
    if (!modalEl || !canRequestEvent) {
      return;
    }

    if (modalHeaderEl) {
      modalHeaderEl.textContent = "Request Event";
    }

    resetModalFields();
    applyDefaultDateRange(baseDate);

    modalEl.style.display = "flex";
    document.body.style.overflow = "hidden";
  };

  const closeModal = () => {
    if (!modalEl) {
      return;
    }

    modalEl.style.display = "none";
    document.body.style.overflow = "";
    resetModalFields();
  };

  const parseErrorMessage = async (response) => {
    const payload = await response.json().catch(() => ({}));

    if (payload?.errors && typeof payload.errors === "object") {
      const firstError = Object.values(payload.errors)
        .flat()
        .find((value) => typeof value === "string");

      if (typeof firstError === "string" && firstError.trim() !== "") {
        return firstError;
      }
    }

    if (typeof payload?.message === "string" && payload.message.trim() !== "") {
      return payload.message;
    }

    return "Unable to submit event request.";
  };

  const submitEventRequest = async () => {
    if (!canRequestEvent || !eventRequestEndpoint) {
      setFeedback("You are not authorized to submit event requests.");
      return;
    }

    const payload = {
      organization_id: organizationEl ? organizationEl.value.trim() : "",
      name: titleEl ? titleEl.value.trim() : "",
      location: locationEl ? locationEl.value.trim() : "",
      desc_text: descriptionEl ? descriptionEl.value.trim() : "",
      start_time: startDateEl ? startDateEl.value : "",
      end_time: endDateEl ? endDateEl.value : "",
    };

    if (!payload.organization_id || !payload.name || !payload.location || !payload.desc_text || !payload.start_time || !payload.end_time) {
      setFeedback("Please complete all fields before submitting.");
      return;
    }

    if (new Date(payload.end_time) < new Date(payload.start_time)) {
      setFeedback("End date and time must be after the start date and time.");
      return;
    }

    const originalLabel = addBtnEl ? addBtnEl.textContent : "";

    if (addBtnEl) {
      addBtnEl.disabled = true;
      addBtnEl.textContent = "Sending...";
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
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const errorMessage = await parseErrorMessage(response);
        throw new Error(errorMessage);
      }

      setFeedback("Event request submitted successfully.", "success");
      window.setTimeout(() => {
        closeModal();
      }, 700);
    } catch (error) {
      const message =
        error instanceof Error && error.message
          ? error.message
          : "Unable to submit event request.";
      setFeedback(message);
    } finally {
      if (addBtnEl) {
        addBtnEl.disabled = false;
        addBtnEl.textContent = originalLabel || "Send Event Request";
      }
    }
  };

  const calendarHeaderToolbar = {
    left: "prev,next",
    center: "title",
    right: "dayGridMonth,timeGridWeek,timeGridDay",
  };

  const calendar = new Calendar(calendarWrapper, {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    selectable: canRequestEvent,
    initialView: "dayGridMonth",
    initialDate: `${newDate.getFullYear()}-${getDynamicMonth()}-07`,
    headerToolbar: calendarHeaderToolbar,
    events: "/api/events/calendar",
    select(info) {
      if (!canRequestEvent) {
        return;
      }

      openModalForRequest(new Date(info.startStr));
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

  if (addBtnEl) {
    addBtnEl.addEventListener("click", submitEventRequest);
  }

  if (openModalBtnEl) {
    openModalBtnEl.addEventListener("click", () => {
      openModalForRequest(new Date());
    });
  }

  calendar.render();

  document.querySelectorAll(".modal-close-btn").forEach((btn) => {
    btn.addEventListener("click", closeModal);
  });

  window.addEventListener("click", (event) => {
    if (event.target === modalEl) {
      closeModal();
    }
  });
}

export default calendarInit;
