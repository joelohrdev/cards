import type { TagSummary } from '@/types';

export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export type ImportSummary = {
    id: number;
    original_filename: string;
    status: ImportStatus;
    total_rows: number;
    processed_rows: number;
    imported_rows: number;
    failed_rows: number;
    tag: TagSummary | null;
    created_at: string;
    completed_at: string | null;
};
