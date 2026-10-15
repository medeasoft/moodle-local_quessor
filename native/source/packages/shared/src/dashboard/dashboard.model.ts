export interface AdminStatsDto {
  totalInstitutes: number;
  totalUsers: number;
  totalExams: number;
  totalStudents: number;
  monthlyGrowth: {
    institutes: number;
    users: number;
  };
  recentInstitutes: RecentInstituteDto[];
  activityChart: ChartDataPoint[];
}

export interface InstituteStatsDto {
  totalCourses: number;
  totalExams: number;
  totalStudents: number;
  totalTeachers: number;
  pendingGradings: number;
  completedGradings: number;
  averageScore: number;
  recentExams: RecentExamDto[];
  performanceChart: PerformanceChartDataPoint[];
}

export interface MemberStatsDto {
  assignedCourses: number;
  courses: MemberCourseDto[];
  totalExams: number;
  pendingGradings: number;
  completedGradings: number;
  latestExamId?: string;
  recentGradings: RecentGradingDto[];
  activityChart: ChartDataPoint[];
}

export interface MemberCourseDto {
  id: string;
  name: string;
  examCount: number;
  pendingCount: number;
}

export interface RecentInstituteDto {
  id: string;
  name: string;
  createdAt: number;
}

export interface RecentExamDto {
  id: string;
  title: string;
  courseName?: string;
  createdAt: number;
}

export interface RecentGradingDto {
  id: string;
  examTitle?: string;
  studentName: string;
  score: number | null;
  gradedAt: number;
}

export interface ChartDataPoint {
  date: string;
  count: number;
  [key: string]: any;
}

export interface PerformanceChartDataPoint {
  date: string;
  avgScore: number;
  count: number;
  [key: string]: any;
}
