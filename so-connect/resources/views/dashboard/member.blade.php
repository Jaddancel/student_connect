@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Membership Dashboard</h1>
            <p class="text-muted">Track your events, deadlines, and request statuses</p>
        </div>
    </div>

    <!-- Upper Section: Event & Calendar Card -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Events & Deadlines
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <!-- Calendar Section -->
                        <div class="col-lg-7 mb-4 mb-lg-0">
                            <h6 class="fw-bold mb-3">Calendar</h6>
                            <div class="calendar-placeholder bg-light p-4 rounded" style="min-height: 350px; display: flex; align-items: center; justify-content: center;">
                                <div class="text-center text-muted">
                                    <i class="fas fa-calendar fa-3x mb-3 opacity-50"></i>
                                    <p>Calendar Integration & Event Timeline</p>
                                    <small class="text-muted">(Add your calendar implementation here)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Events & Deadlines Section -->
                        <div class="col-lg-5">
                            <h6 class="fw-bold mb-3">Upcoming Events & Deadlines</h6>
                            <div class="timeline">
                                <!-- Event Entry 1 -->
                                <div class="d-flex gap-3 mb-3">
                                    <div>
                                        <div class="timeline-marker bg-success rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-check text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">Annual Meeting</h6>
                                        <small class="text-muted d-block">Due: April 15, 2026</small>
                                        <span class="badge bg-success">Registered</span>
                                    </div>
                                </div>

                                <!-- Event Entry 2 -->
                                <div class="d-flex gap-3 mb-3">
                                    <div>
                                        <div class="timeline-marker bg-warning rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-clock text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">Document Submission</h6>
                                        <small class="text-muted d-block">Due: March 30, 2026</small>
                                        <span class="badge bg-warning">Pending</span>
                                    </div>
                                </div>

                                <!-- Event Entry 3 -->
                                <div class="d-flex gap-3">
                                    <div>
                                        <div class="timeline-marker bg-danger rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-exclamation text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">Membership Renewal</h6>
                                        <small class="text-muted d-block">Due: March 28, 2026</small>
                                        <span class="badge bg-danger">Urgent</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lower Section: Status Cards -->
    <div class="row g-4">
        <!-- Membership Request Status -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-info text-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-user-check me-2"></i>Membership Request
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold text-info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <h5 class="text-center mb-3">Pending Review</h5>
                    <div class="status-details">
                        <p class="mb-2">
                            <small class="text-muted">Status:</small>
                            <span class="badge bg-info">In Progress</span>
                        </p>
                        <p class="mb-2">
                            <small class="text-muted">Submitted:</small>
                            <span>March 15, 2026</span>
                        </p>
                        <p class="mb-0">
                            <small class="text-muted">Expected:</small>
                            <span>April 5, 2026</span>
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <a href="#" class="btn btn-sm btn-outline-info w-100">View Details</a>
                </div>
            </div>
        </div>

        <!-- Event Request Status -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Event Request
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <h5 class="text-center mb-3">Approved</h5>
                    <div class="status-details">
                        <p class="mb-2">
                            <small class="text-muted">Status:</small>
                            <span class="badge bg-success">Approved</span>
                        </p>
                        <p class="mb-2">
                            <small class="text-muted">Registered for:</small>
                            <span>2 Events</span>
                        </p>
                        <p class="mb-0">
                            <small class="text-muted">Confirmed:</small>
                            <span>March 22, 2026</span>
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <a href="#" class="btn btn-sm btn-outline-success w-100">View Events</a>
                </div>
            </div>
        </div>

        <!-- Documents Request Status -->
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-warning text-dark py-3">
                    <h6 class="mb-0">
                        <i class="fas fa-file-upload me-2"></i>Documents Request
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-6 fw-bold text-warning">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                    <h5 class="text-center mb-3">Action Required</h5>
                    <div class="status-details">
                        <p class="mb-2">
                            <small class="text-muted">Status:</small>
                            <span class="badge bg-warning">Pending Documents</span>
                        </p>
                        <p class="mb-2">
                            <small class="text-muted">Documents needed:</small>
                            <span>3 files</span>
                        </p>
                        <p class="mb-0">
                            <small class="text-muted">Deadline:</small>
                            <span>March 30, 2026</span>
                        </p>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <a href="#" class="btn btn-sm btn-outline-warning w-100">Upload Documents</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .timeline-marker {
        min-width: 40px;
        min-height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
    }

    .card-header {
        border-bottom: none;
    }

    .status-details p {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>
@endsection
