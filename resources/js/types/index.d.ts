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
        user: User;
        can: {
            manageAccounts: boolean;
        };
    };
    flash: {
        success?: string;
    };
};
