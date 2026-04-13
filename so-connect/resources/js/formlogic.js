export function registerMembershipFormLogic(Alpine) {
    Alpine.data("membershipRegistrationForm", (organizationsByType = {}) => ({
        selectedType: "0",
        selectedOrganization: "",
        organizationsByType,

        get availableOrganizations() {
            const typeKey = String(this.selectedType);
            return Array.isArray(this.organizationsByType[typeKey])
                ? this.organizationsByType[typeKey]
                : [];
        },

        updateOrgList() {
            this.selectedOrganization = "";
        },
    }));
}
