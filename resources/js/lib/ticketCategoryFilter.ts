export type CategoryTreeRow = {
    id: number;
    name: string;
    parent_id: number | null;
};

/**
 * All category ids in the subtree rooted at rootId (including rootId).
 * Keep algorithm in sync with tests/Unit/TicketCategorySubtreeTest.php.
 */
export function collectDescendantCategoryIds(categories: CategoryTreeRow[], rootId: number): Set<number> {
    const ids = new Set<number>([rootId]);
    const queue = [rootId];
    while (queue.length > 0) {
        const parentId = queue.shift()!;
        for (const c of categories) {
            if (c.parent_id === parentId && !ids.has(c.id)) {
                ids.add(c.id);
                queue.push(c.id);
            }
        }
    }
    return ids;
}

/**
 * True if the ticket belongs to the selected category or any of its descendants.
 * Legacy rows without ticketCategoryId match by denormalized name when it equals an allowed category name.
 */
export function ticketMatchesCategorySubtreeFilter(
    categories: CategoryTreeRow[],
    filterCategoryId: number,
    ticketCategoryId: number | null,
    ticketCategoryName: string,
): boolean {
    const allowedIds = collectDescendantCategoryIds(categories, filterCategoryId);
    if (ticketCategoryId != null && allowedIds.has(ticketCategoryId)) {
        return true;
    }
    if (ticketCategoryId == null && ticketCategoryName) {
        for (const id of allowedIds) {
            const row = categories.find((c) => c.id === id);
            if (row && row.name === ticketCategoryName) {
                return true;
            }
        }
    }
    return false;
}
