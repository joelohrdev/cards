import {
    Archive,
    BookOpen,
    Box,
    Briefcase,
    Layers,
    PanelTop,
    Rows3,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const locationIcons: Record<string, LucideIcon> = {
    box: Box,
    binder: BookOpen,
    case: Briefcase,
    shelf: PanelTop,
    row: Rows3,
    page: Layers,
    other: Archive,
};
