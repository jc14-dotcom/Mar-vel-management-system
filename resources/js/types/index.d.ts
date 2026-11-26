export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_active: boolean;
    roles?: { name: string; label: string }[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        can: {
            manageAccounts: boolean;
            managePermissions: boolean;
            manageProjects: boolean;
            manageSettings: boolean;
            viewVouchers: boolean;
            createVouchers: boolean;
            editVouchers: boolean;
            cancelVouchers: boolean;
        };
    };
    flash: {
        success?: string;
        warning?: string;
        createdProject?: { id: number; name: string } | null;
    };
};
