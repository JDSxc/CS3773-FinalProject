document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput") as HTMLInputElement;
  const statusFilter = document.getElementById("statusFilter") as HTMLSelectElement;
  const sortBy = document.getElementById("sortBy") as HTMLSelectElement;
  const tableBody = document.getElementById("ordersBody") as HTMLTableSectionElement;

  if (!searchInput || !statusFilter || !sortBy || !tableBody) return;

  const rows = Array.from(tableBody.querySelectorAll("tr"));

  function filterRows() {
    const search = searchInput.value.toLowerCase();
    const status = statusFilter.value;

    rows.forEach(row => {
      const cells = row.querySelectorAll("td");

      const id = cells[0]?.textContent?.toLowerCase() || "";
      const customer = cells[1]?.textContent?.toLowerCase() || "";
      const rowStatus = cells[5]?.textContent || "";

      const matchesSearch =
        id.includes(search) || customer.includes(search);

      const matchesStatus =
        status === "all" || rowStatus === status;

      row.style.display = matchesSearch && matchesStatus ? "" : "none";
    });
  }

  function sortRows() {
    const type = sortBy.value;

   const sorted = [...rows].toSorted((a, b) => {
  const aCells = a.querySelectorAll("td");
  const bCells = b.querySelectorAll("td");

  if (type === "date") {
    return (aCells[2].textContent || "").localeCompare(bCells[2].textContent || "");
  }

  if (type === "total") {
    const aTotal = Number.parseFloat((aCells[4].textContent || "").replace("$", ""));
    const bTotal = Number.parseFloat((bCells[4].textContent || "").replace("$", ""));
    return aTotal - bTotal;
  }

  return 0;
});

    tableBody.innerHTML = "";
    sorted.forEach(r => tableBody.append(r));
  }

  // Events
  searchInput.addEventListener("input", filterRows);
  statusFilter.addEventListener("change", filterRows);

  sortBy.addEventListener("change", () => {
    sortRows();
    filterRows();
  });

  // View button click
  tableBody.addEventListener("click", (e) => {
    const target = e.target as HTMLElement;

    if (target.classList.contains("view-btn")) {
      const row = target.closest("tr");
      const id = row?.children[0]?.textContent;

      alert(`Viewing Order #${id}`);
    }
  });
});
